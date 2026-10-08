<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Search;

use App\DataTransferObjects\Search\Aggregation;
use App\DataTransferObjects\Search\Filter;
use App\DataTransferObjects\Search\FilterType;
use App\DataTransferObjects\Search\QueryConfig;

class QueryBuilder
{
    protected array $filters = [];

    protected array $aggregations = [];

    protected array $sourceFields = [];

    protected int $size = 0;

    protected array $sort = [];

    protected array $keywordFields = [
        'state',
        'author',
        'status',
    ];

    public function addFilter(Filter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    public function addAggregation(Aggregation $agg): self
    {
        $this->aggregations[$agg->name] = $agg->definition;

        return $this;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function selectFields(array $fields): self
    {
        $this->sourceFields = $fields;

        return $this;
    }

    /**
     * Add one or multiple sort criteria.
     *
     * @param array|string $sort
     * @return $this
     */
    public function addSort(array | string $sort): self
    {
        if (is_array($sort)) {
            $this->sort = array_merge($this->sort, $sort);
        } else {
            $this->sort[] = $sort;
        }

        return $this;
    }

    public function fromConfig(QueryConfig $config): self
    {
        foreach ($config->filters as $filter) {
            $this->addFilter($filter);
        }

        foreach ($config->aggregations as $agg) {
            $this->addAggregation($agg);
        }

        $this->selectFields($config->fields);
        $this->setSize($config->size);

        if (!empty($config->sort)) {
            $this->addSort($config->sort);
        }

        return $this;
    }

    public function build(): array
    {
        $must = [];

        foreach ($this->filters as $filter) {
            $field = $filter->field;
            // If it's a term filter and the field is in keywordFields,
            // use the .keyword subfield for exact matching
            if ($filter->type === FilterType::TERM && in_array($field, $this->keywordFields, true)) {
                $field .= '.keyword';
            }

            $must[] = match ($filter->type) {
                FilterType::RANGE => ['range' => [$field => $filter->value]],
                FilterType::TERMS => ['terms' => [$field => $filter->value]],
                default => ['term' => [$field => $filter->value]],
            };
        }

        $query = [
            'size' => $this->size,
            'query' => ['bool' => ['must' => $must]],
        ];

        if (!empty($this->aggregations)) {
            $query['aggs'] = $this->aggregations;
        }

        if (!empty($this->sourceFields)) {
            $query['_source'] = $this->sourceFields;
        }

        if (!empty($this->sort)) {
            $query['sort'] = $this->sort;
        }

        return $query;
    }
}
