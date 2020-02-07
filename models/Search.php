<?php

namespace wdmg\search\models;

use Yii;
use yii\db\Expression;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\behaviors\TimestampBehavior;
use wdmg\helpers\TextAnalyzer;
use yii\helpers\Html;

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


    public function generateSnippets($content = null, $keywords = null, $options = [])
    {

        $snippets = [];
        if (!empty($content) && is_array($keywords)) {

            foreach ($keywords as $keyword) {
                $count_in = 0;

                $min_words_before = 2;
                $max_words_before = (isset($options['before'])) ? $options['before'] : 6;
                $words_before = $min_words_before . ',' . intval($max_words_before);

                $min_words_after = 2;
                $max_words_after = (isset($options['after'])) ? $options['after'] : 4;
                $words_after = $min_words_after . ',' . intval($max_words_after);

                preg_match_all('/(?:\w+){' . $words_before . '}\s+((?:\b' . $keyword . '*?)\w+)\s+(?:\w+){' . $words_after . '}/iu', $content, $matches, PREG_OFFSET_CAPTURE);
                foreach ($matches as $key => $match) {


                    foreach ($match as $part) {

                        if ($count_in >= 3)
                            continue;

                        if ($tag = $options['tag'])
                            $snippet = preg_replace('/(?:\b' . $keyword . '*?)\w+/iu', Html::tag($tag, '$0'), $part[0]);

                        //$snippet = preg_replace('/(?:\b' . $keyword . '*?)\w+/iu', Html::tag($tag, '$0', (isset($options['tag_options'])) ? $options['tag_options'] : false), $part[0]);

                        if (!empty($snippet)) {
                            if (empty($snippets))
                                $snippets[$keyword][] = mb_convert_case(mb_substr(trim($snippet), 0, 1), MB_CASE_TITLE) . mb_substr(trim($snippet), 1);
                            else
                                $snippets[$keyword][] = trim($snippet);

                            $count_in++;
                        }

                    }
                }
            }
        }

        return $snippets;
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
                $min_length = 2;

            if (is_null($stop_words = $this->module->analyzerOptions['stop_words']))
                $stop_words = null;

            if (is_null($stop_words = $this->module->analyzerOptions['weights']))
                $weights = null;

        }

        // Default snippets options
        $snippetsOptions = [
            'before' => 6,
            'after' => 4,
            'tag' => false,
            'length' => 255,
            'delimiter' => '…'
        ];

        // Load snippets options from module config
        if (!is_null($this->module->snippetOptions)) {

            if (isset($this->module->snippetOptions['max_words_before']))
                $snippetsOptions['before'] = $this->module->snippetOptions['max_words_before'];

            if (isset($this->module->snippetOptions['max_words_after']))
                $snippetsOptions['after'] = $this->module->snippetOptions['max_words_after'];

            if (isset($this->module->snippetOptions['bolder_tag']))
                $snippetsOptions['tag'] = $this->module->snippetOptions['bolder_tag'];

            if (isset($this->module->snippetOptions['max_length']))
                $snippetsOptions['length'] = $this->module->snippetOptions['max_length'];

            if (isset($this->module->snippetOptions['delimiter']))
                $snippetsOptions['delimiter'] = $this->module->snippetOptions['delimiter'];

        }


        \Yii::beginProfile('search-indexing');
        if (!is_null($model) && !is_null($context) && !is_null($options) && $action !== 3) {

            if (isset($options['fields'])) {

                $fields = $options['fields'];
                if ($model instanceof \yii\db\ActiveRecord && is_array($fields)) {

                    $title = null;
                    if (isset($options['title'])) {
                        $title = $model[$options['title']];
                    }

                    $url = null;
                    if (isset($options['url'])) {
                        $url = $model[$options['url']];
                    }

                    $attributes = array_uintersect($fields, array_keys($model->getAttributes()), 'strcasecmp');

                    // Здесь будем хранить весь контент модели
                    $content = '';

                    // Склеиваем весь доступный контент модели
                    foreach ($attributes as $attribute) {
                        if (!empty($content))
                            $content .= " " . $model->$attribute;
                        else
                            $content .= $model->$attribute;
                    }

                    //
                    $hash = md5($content);

                    // Подготавливаем и анализируем контент, разбиваем текст на массив слов
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

                    // Сортируем найденные слова и делаем выборку
                    $words = $analyzer->sorting($sorting, $max_words);
                    $keywords = $words["words"];
                    \Yii::endProfile('search-indexing-text-analyzer');

                    // Генерируем поисковые сниппеты
                    \Yii::beginProfile('search-indexing-generate-snippets');
                    $snippets = $this->generateSnippets($content, array_keys($keywords), $snippetsOptions);
                    \Yii::endProfile('search-indexing-generate-snippets');

                    // Приступаем к морфологическому анализу
                    if (is_array($keywords)) {
                        $predict = [];

                        \Yii::beginProfile('search-indexing-phpmorphy');
                        $morphy = new \phpMorphy(null, 'ru_RU', [
                            'storage' => \phpMorphy::STORAGE_FILE,
                            'with_gramtab' => false,
                            'predict_by_suffix' => true,
                            'predict_by_db' => true
                        ]);

                        // Получаем базовые формы слов
                        foreach ($keywords as $keyword => $stat) {
                            if ($base = $morphy->lemmatize(mb_strtoupper(str_ireplace("ё", "е", $keyword), "UTF-8"))) {
                                $predict[mb_strtolower($base[0], "UTF-8")] = $stat[$analyze_by];
                            } else {
                                $predict[$keyword] = $stat[$analyze_by];
                            }
                        }
                        \Yii::endProfile('search-indexing-phpmorphy');


                        // Проверяем, нет ли такой страницы в индексе, если нет - добавляем
                        \Yii::beginProfile('search-indexing-save');
                        if (self::find()->where(['url' => $url])->exists()) {
                            $search = self::find()->where(['url' => $url])->one();

                            // Если хеши совпадают, значит контент не изменился - останавливаемся
                            if ($hash == $search->hash)
                                return 2;

                        } else {
                            // Создаём новый экземпляр модели
                            $search = new self();
                        }

                        // Загружаем данные о странице, где были найдены ключевые слова
                        $search->setAttributes([
                            'title' => $title,
                            'url' => $url,
                            'context' => $context,
                            'hash' => $hash,
                            'snippets' => serialize($snippets)
                        ]);

                        // Проверяем модель
                        if ($search->validate()) {

                            // Сохраняем модель данных о странице
                            if ($search->save()) {
                                $item_id = $search->id;

                                // Теперь добавляем каждое из поисковых слов
                                foreach ($predict as $keyword => $weight) {

                                    // Загружаем модель ключевых слов
                                    $searchKeywords = new SearchKeywords();
                                    $searchKeywords->setAttributes([
                                        'keyword' => $keyword,
                                    ]);

                                    // Проходим валидацию
                                    if ($searchKeywords->validate()) {

                                        // Проверяем нет ли такого слова в базе
                                        $keyword_id = null;
                                        $query = $searchKeywords::find()->where(['keyword' => $keyword]);
                                        if ($exists = $query->exists()) {
                                            $keyword_id = $query->one()->id;
                                        } else {
                                            if ($searchKeywords->save()) {
                                                $keyword_id = $searchKeywords->id;
                                            }
                                        }

                                        // Записываем связь поискового слово к странице в поисковый индекс
                                        if (!is_null($keyword_id)) {

                                            // Загружаем аттрибуты модели поискового индекса
                                            $searchIndex = new SearchIndex();
                                            $searchIndex->setAttributes([
                                                'item_id' => $item_id,
                                                'keyword_id' => $keyword_id,
                                                'weight' => $weight,
                                            ]);

                                            // Если всё вёрно, сохраняем модель
                                            if ($searchIndex->validate()) {
                                                $searchIndex->save();
                                                /*if ($searchIndex->save())
                                                    return 1;
                                                else
                                                    return -1;*/
                                            }
                                        }
                                    }
                                }
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
