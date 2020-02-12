<?php

namespace wdmg\search\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use wdmg\search\models\Search;

/**
 * SearchCommon represents the model behind the search form of `wdmg\search\models\Search`.
 */
class SearchCommon extends Search
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['title', 'context', 'hash', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function filter($params)
    {
        $query = Search::find();

        // add conditions that should always apply here
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // base filtering conditions
        $query->where(["NOT", [
            'snippets' => null,
        ]])->orWhere([
            'snippets' => "",
        ]);

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
        ]);

        $query->andFilterWhere(['like', 'title', $this->title])
            ->orFilterWhere(['like', 'url', $this->title]);

        $query->andFilterWhere(['like', 'created_at', $this->created_at])
            ->andFilterWhere(['like', 'updated_at', $this->updated_at]);

        if($this->context !== "*")
            $query->andFilterWhere(['like', 'context', $this->context]);

        //var_dump($query->createCommand()->getRawSql());die();
        return $dataProvider;
    }

}
