<?php

namespace wdmg\search\models;

use Yii;
use yii\db\Expression;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use wdmg\helpers\TextAnalyzer;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%search}}".
 *
 * @property int $id
 * @property string $title
 * @property string $url
 * @property string $context
 * @property string $hash
 * @property string $snippets
 * @property int $created_at
 * @property int $updated_at
 *
 *
 */
class Search extends ActiveRecord
{

    public $query;
    public $module;
    public $snippet;

    private $_keywords;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if (!($this->module = Yii::$app->getModule('admin/search')))
            $this->module = Yii::$app->getModule('search');

    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%search}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'created_at',
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'updated_at',
                ],
                'value' => new Expression('NOW()'),
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        $rules = [
            [['title', 'url'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['url'], 'url'],
            [['context'], 'string', 'max' => 24],
            [['snippets'], 'string'],

            ['query', 'string', 'max' => 128],

            [['created_at', 'updated_at', 'hash'], 'safe'],
        ];

        return $rules;
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app/modules/search', 'ID'),
            'title' => Yii::t('app/modules/search', 'Title'),
            'url' => Yii::t('app/modules/search', 'URL'),
            'context' => Yii::t('app/modules/search', 'Context'),
            'hash' => Yii::t('app/modules/search', 'Hash'),
            'snippets' => Yii::t('app/modules/search', 'Snippets'),
            'created_at' => Yii::t('app/modules/search', 'Created at'),
            'updated_at' => Yii::t('app/modules/search', 'Updated at')
        ];
    }

    public function search($request = null) {

        if (is_null($request))
            return false;

        $results = null;
        $request = preg_replace('/[^\w\s\-\+\"]/u', ' ', $request);
        $expanded = preg_split('/\s+/', $request);

        if (is_null($searchAccuracy = intval($this->module->searchAccuracy)))
            $searchAccuracy = 90;

        // Default snippets options
        $snippetOptions = [
            'length' => 255,
            'delimiter' => '…'
        ];

        // Load snippets options from module config
        if (!is_null($this->module->snippetOptions)) {

            if (isset($this->module->snippetOptions['max_length']))
                $snippetOptions['length'] = $this->module->snippetOptions['max_length'];

            if (isset($this->module->snippetOptions['delimiter']))
                $snippetOptions['delimiter'] = $this->module->snippetOptions['delimiter'];

        }

        //
        $morphy = new \phpMorphy(null, 'ru_RU', [
            'storage' => \phpMorphy::STORAGE_FILE,
            'with_gramtab' => false,
            'predict_by_suffix' => true,
            'predict_by_db' => true
        ]);

        foreach ($expanded as $key => $keyword) {
            if (mb_strlen($keyword) >= 3) {
                if ($base = $morphy->lemmatize(mb_strtoupper(str_ireplace("ё", "е", $keyword), "UTF-8"))) {
                    $this->_keywords[] = mb_strtolower($base[0], "UTF-8");
                } else {
                    $this->_keywords[] = $keyword;
                }
            }
        }

        if (!empty($this->_keywords)) {

            $searchKeywords = new SearchKeywords();
            $query = $searchKeywords->find()
                ->select('id')
                ->where(['keyword' => $this->_keywords])
                ->orderBy('id');

            //var_dump($query->createCommand()->getRawSql());
            $keyword_ids = ArrayHelper::getColumn($query->all(), 'id');

            if (!empty($keyword_ids)) {

                $searchIndex = new SearchIndex();
                $query = $searchIndex->find()->select('item_id')
                    ->where(['keyword_id' => $keyword_ids])
                    ->andWhere('weight >= :accuracy', [':accuracy' => $searchAccuracy])
                    ->groupBy('item_id')
                    ->orderBy('`weight` DESC');

                //var_dump($query->createCommand()->getRawSql());
                $items_ids = ArrayHelper::getColumn($query->all(), 'item_id');

                if (!empty($items_ids)) {

                    $search = new self();
                    $items = $search->find()->where(['id' => $items_ids])->all();

                    foreach ($items as $item) {

                        $snippets = [];
                        if ($all_snippets = unserialize($item->snippets)) {
                            foreach ($keyword_ids as $id) {
                                if (isset($all_snippets[$id])) {
                                    foreach ($all_snippets[$id] as $snippet) {
                                        if (!empty($snippet)) {
                                            $snippets[] = $snippet;
                                        }
                                    }
                                }
                            }
                        }

                        $item->snippet = "";
                        $delimiter = $snippetOptions['delimiter'];
                        if (!empty($snippets))
                            $item->snippet = implode(" " . $delimiter . " ", $snippets) . $delimiter;
                        /*elseif (!empty($content))
                            $item->snippet = mb_substr($content, 0, intval($snippetOptions['length'])) . $delimiter;*/

                        unset($item->snippets);

                    }

                    $results = $items;
                }
            }
        }

        return $results;
    }


    /**
     * @param null $model
     * @param null $context
     * @param null $options
     * @param int $action
     * @return int, state where: -1 - has error occurred, 0 - no indexing, 1 - success indexing, 2 - already in index (not updated)
     */
    public function indexing($model = null, $context = null, $options = null, $action = 1) {


        if (is_null($max_words = $this->module->indexingOptions['max_words']))
            $max_words = 50;

        if (is_null($analyze_by = $this->module->indexingOptions['analyze_by']))
            $analyze_by = 'relevance';

        if (is_null($max_execution_time = $this->module->indexingOptions['max_execution_time']))
            $max_execution_time = 30;

        ini_set('max_execution_time', intval($max_execution_time));

        if (!is_null($this->module->analyzerOptions)) {

            if (is_null($min_length = $this->module->analyzerOptions['min_length']))
                $min_length = 3;

            if (is_null($stop_words = $this->module->analyzerOptions['stop_words']))
                $stop_words = null;

            if (is_null($stop_words = $this->module->analyzerOptions['weights']))
                $weights = null;

        }

        // Default snippets options
        $snippetOptions = [
            'before' => 6,
            'after' => 4,
            'tag' => false,
            'length' => 255,
            'delimiter' => '…'
        ];

        // Load snippets options from module config
        if (!is_null($this->module->snippetOptions)) {

            if (isset($this->module->snippetOptions['max_words_before']))
                $snippetOptions['before'] = $this->module->snippetOptions['max_words_before'];

            if (isset($this->module->snippetOptions['max_words_after']))
                $snippetOptions['after'] = $this->module->snippetOptions['max_words_after'];

            if (isset($this->module->snippetOptions['bolder_tag']))
                $snippetOptions['tag'] = $this->module->snippetOptions['bolder_tag'];

            if (isset($this->module->snippetOptions['max_length']))
                $snippetOptions['length'] = $this->module->snippetOptions['max_length'];

            if (isset($this->module->snippetOptions['delimiter']))
                $snippetOptions['delimiter'] = $this->module->snippetOptions['delimiter'];

        }


        \Yii::beginProfile('search-indexing');
        if (!is_null($model) && !is_null($context) && !is_null($options) && $action !== 3) {

            if (isset($options['fields'])) {
                $fields = $options['fields'];
                if ($model instanceof \yii\db\ActiveRecord && is_array($fields)) {

                    // Checking, perhaps the properties of the model does not meet the conditions allowing indexing
                    if (isset($options['conditions'])) {
                        foreach ($options['conditions'] as $prop => $value) {
                            if (!is_null($model->$prop)) {

                                if (!($model->$prop == $value))
                                    return 0;

                            }
                        }
                    }

                    // Required title attribute for search results
                    $title = null;
                    if (isset($options['title'])) {

                        if (!($title = $model->getAttribute($options['title'])))
                            $title = $model[$options['title']];

                    }

                    // Required URL attribute for search results
                    $url = null;
                    if (isset($options['url'])) {

                        if (!($url = $model->getAttribute($options['url'])))
                            $url = $model[$options['url']];

                    }

                    // Get only the attributes that exist in the model
                    $attributes = null;
                    if (is_array($options['fields'])) {
                        $attributes = array_uintersect($options['fields'], array_keys($model->getAttributes()), 'strcasecmp');
                    }

                    // Here we will store all indexing content
                    $content = '';

                    // Glue all available model content
                    foreach ($attributes as $attribute) {
                        if (!empty($content))
                            $content .= " " . $model->$attribute;
                        else
                            $content .= $model->$attribute;
                    }

                    // Create a hash for comparing content
                    $hash = md5($content);

                    // Prepare and analyze the content, break the text into an array of words
                    \Yii::beginProfile('search-indexing-text-analyzer');
                    $analyzer = new TextAnalyzer();

                    if (!empty($min_length) && is_int($min_length))
                        $analyzer->min_length = intval($min_length);

                    if (!empty($stop_words) && is_array($stop_words))
                        $analyzer->stop_words = $stop_words;

                    if (!empty($weights) && is_array($weights))
                        $analyzer->weights = $weights;

                    $analyzer->process($content);

                    if ($analyze_by == 'density')
                        $sorting = 1;
                    elseif ($analyze_by == 'prominence')
                        $sorting = 2;
                    elseif ($analyze_by == 'relevance')
                        $sorting = 3;
                    else
                        $sorting = 4;

                    // Sort the found words and make a selection
                    $words = $analyzer->sorting($sorting, $max_words);
                    $keywords = $words["words"];
                    \Yii::endProfile('search-indexing-text-analyzer');

                    // Generate search snippets
                    \Yii::beginProfile('search-indexing-generate-snippets');
                    $all_snippets = $this->module->generateSnippets($content, array_keys($keywords), $snippetOptions);
                    \Yii::endProfile('search-indexing-generate-snippets');

                    // Getting to the morphological analysis
                    if (is_array($keywords)) {

                        // Collection of keywords with the meaning of weights and snippets
                        $collection = [];

                        \Yii::beginProfile('search-indexing-phpmorphy');
                        $morphy = new \phpMorphy(null, 'ru_RU', [
                            'storage' => \phpMorphy::STORAGE_FILE,
                            'with_gramtab' => false,
                            'predict_by_suffix' => true,
                            'predict_by_db' => true
                        ]);

                        // Get the basic forms of words
                        foreach ($keywords as $keyword => $stat) {
                            if ($base = $morphy->lemmatize(mb_strtoupper(str_ireplace("ё", "е", $keyword), "UTF-8"))) {
                                $collection[] = [
                                    'keyword' => $keyword,
                                    'base' => mb_strtolower($base[0], "UTF-8"),
                                    'weight' => $stat[$analyze_by],
                                    'snippet' => isset($all_snippets[$keyword]) ? $all_snippets[$keyword] : null
                                ];
                            } else {
                                $collection[] = [
                                    'keyword' => $keyword,
                                    'base' => $keyword,
                                    'weight' => $stat[$analyze_by],
                                    'snippet' => isset($all_snippets[$keyword]) ? $all_snippets[$keyword] : null
                                ];
                            }
                        }
                        \Yii::endProfile('search-indexing-phpmorphy');


                        // Check if there is such a page in the index, if not, add
                        \Yii::beginProfile('search-indexing-save');
                        if (self::find()->where(['url' => $url])->exists()) {
                            $search = self::find()->where(['url' => $url])->one();

                            // If the hashes match, then the content has not changed - stop
                            if ($hash == $search->hash)
                                return 2;

                        } else {
                            // Create a new instance of the model
                            $search = new self();
                        }

                        // Set the data about the page where keywords were found
                        $search->setAttributes([
                            'title' => $title,
                            'url' => $url,
                            'context' => $context,
                            'hash' => $hash
                        ]);

                        // Check model
                        if ($search->validate()) {

                            // First, save the page data model, because we need her id
                            if ($search->save()) {

                                $isOk = true;
                                $item_id = $search->id;

                                // Now add each of the search words
                                $snippets = [];
                                foreach ($collection as $item) {

                                    // Set the attributes of keyword model
                                    $searchKeywords = new SearchKeywords();
                                    $searchKeywords->setAttributes([
                                        'keyword' => $item['base'],
                                    ]);

                                    // Pass keyword validation
                                    if ($searchKeywords->validate()) {

                                        // Check if there is such a word in the database
                                        $keyword_id = null;
                                        $query = $searchKeywords::find()->where(['keyword' => $item['base']]);
                                        if ($exists = $query->exists()) {
                                            $keyword_id = $query->one()->id;
                                        } else {
                                            if ($searchKeywords->save()) {
                                                $keyword_id = $searchKeywords->id;
                                            }
                                        }

                                        // Write the link of the search word to the page in the search index
                                        if (!is_null($keyword_id)) {

                                            // Set the attributes of the search index model
                                            $searchIndex = new SearchIndex();
                                            $searchIndex->setAttributes([
                                                'item_id' => $item_id,
                                                'keyword_id' => $keyword_id,
                                                'weight' => $item['weight'],
                                            ]);

                                            // If everything is correct, save the model
                                            if ($searchIndex->validate()) {

                                                if (!($searchIndex->save()))
                                                    $isOk = false;

                                            }

                                            if (isset($item['snippet']))
                                                $snippets[$keyword_id] = $item['snippet'];

                                        }
                                    }
                                }

                                // Save the resulting snippets
                                $search->snippets = serialize($snippets);
                                if ($search->update() && $isOk)
                                    return 1;
                                else
                                    return -1;

                            }
                        }

                        \Yii::endProfile('search-indexing-save');
                    }
                } else {
                    return -1;
                }
            }

        }

        \Yii::endProfile('search-indexing');
        return 0;

    }


}
