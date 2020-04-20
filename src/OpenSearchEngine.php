<?php
/**
 * This file is part of ruogoo.
 *
 * Created by HyanCat.
 *
 * Copyright (C) HyanCat. All rights reserved.
 */

namespace xiaoguo\OpenSearch;

require_once __DIR__ . '/../sdk/OpenSearch/Autoloader/Autoloader.php';

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use OpenSearch\Client\DocumentClient;
use OpenSearch\Client\OpenSearchClient;
use OpenSearch\Client\SearchClient;
use OpenSearch\Generated\Common\OpenSearchResult;
use OpenSearch\Util\SearchParamsBuilder;

class OpenSearchEngine extends Engine
{
    protected $client;
    protected $documentClient;
    protected $searchClient;

    protected $config;

    public function __construct(Repository $config)
    {
        $accessKeyID = $config->get('scout.opensearch.accessKeyID');
        $accessKeySecret = $config->get('scout.opensearch.accessKeySecret');
        $host = $config->get('scout.opensearch.host');
        $this->config = $config;


        $this->client = new OpenSearchClient($accessKeyID, $accessKeySecret, $host);

        $this->documentClient = new DocumentClient($this->client);
        $this->searchClient = new SearchClient($this->client);
    }

    public function update($models)
    {
        $this->performDocumentsCommand($models, 'ADD');
    }

    public function delete($models)
    {
        $this->performDocumentsCommand($models, 'DELETE');
    }

    public function search(Builder $builder)
    {
        return $this->performSearch($builder, 0, 20);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {

        return $this->performSearch($builder, ($page - 1) * $perPage, $perPage);
    }

    public function mapIds($results)
    {
        $result = $this->checkResults($results);
        if (array_get($result, 'result.num', 0) === 0) {
            return collect();
        }

        return collect(array_get($result, 'result.items'))->pluck('fields.id')->values();
    }

    public function map($results, $model)
    {
        $result = $this->checkResults($results);

        if (array_get($result, 'result.num', 0) === 0) {
            return collect();
        }
        $keys = collect(array_get($result, 'result.items'))->pluck('fields.id')->values()->all();
        $models = $model->whereIn($model->getQualifiedKeyName(), $keys)->get()->keyBy($model->getKeyName());

        return collect(array_get($result, 'result.items'))->map(function ($item) use ($model, $result) {
            $key = $item['fields'];
            return collect($key);

        })->filter()->values();
    }

    public function getTotalCount($results)
    {
        $result = $this->checkResults($results);

        return array_get($result, 'result.total', 0);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $models
     * @param string $cmd
     */
    private function performDocumentsCommand($models, string $cmd)
    {
        if ($models->count() === 0) {
            return;
        }

        $appName = $models->first()->openSearchAppName();
        $tableName = $models->first()->getTable();

        $docs = $models->map(function ($model) use ($cmd) {
            $fields = $model->toSearchableArray();

            if (empty($fields)) {
                return [];
            }

            return [
                'cmd' => $cmd,
                'fields' => $fields,
            ];
        });
        $json = json_encode($docs);
        $this->documentClient->push($json, $appName, $tableName);
    }

    private function performSearch(Builder $builder, $from, $count)
    {

        $params = new SearchParamsBuilder();
        $params->setStart($from);
        $params->setHits($count);
        $params->setFilter('id>0');

        foreach ($builder->wheres as $index => $where) {

            if ($index == 'min_price') {
                $params->addFilter('price>=' . $where);
            } elseif ($index == 'max_price') {
                $params->addFilter('price<=' . $where);
            } elseif ($index == 'school_ids') {
                if (count($where) == 1) {
                    $params->addFilter('school_id=' . $where[0]);
                } else {
                    for ($x = 0; $x < count($where); $x++) {
                        if ($x == 0) {
                            //学校之间是or的关系和其他筛选条件之间是and关系,故此加入小括号
                            $params->addFilter('(school_id=' . $where[$x]);
                        } else {
                            if (count($where) - 1 == $x) {
                                $params->addFilter('school_id=' . $where[$x] . ')', 'OR');

                            } else {
                                $params->addFilter('school_id=' . $where[$x], 'OR');
                            }
                        }
                    }
                }

            } elseif ($index == 'school_type') {
                if (count($where) == 1) {
                    $params->addFilter('school_type=' . $where[0]);
                } else {
                    for ($x = 0; $x < count($where); $x++) {
                        if ($x == 0) {
                            $params->addFilter('(school_type=' . $where[$x]);
                        } else {
                            if (count($where) - 1 == $x) {
                                $params->addFilter('school_type=' . $where[$x] . ')', 'OR');

                            } else {
                                $params->addFilter('school_type=' . $where[$x], 'OR');
                            }
                        }
                    }
                }
            }else {
                $params->addFilter($index . '=' . $where);
            }

        }


        $params->setAppName($builder->model->openSearchAppName());
        if ($builder->index) {
            $params->setQuery("$builder->index:'$builder->query'");
        } else {
            $params->setQuery("'$builder->query'");
        }
        $params->setFormat('fullJson');

        foreach ($builder->orders as $index => $order) {
            $orderType = 1;

            if ($order['direction'] == 'desc') {
                $orderType = 0;
            }
            $params->addSort($order['column'], $orderType);

        }

        return $this->searchClient->execute($params->build());
    }


    private function checkResults($results)
    {
        $result = [];
        if ($results instanceof OpenSearchResult) {
            $result = json_decode($results->result, true);
        }

        return $result;
    }
}
