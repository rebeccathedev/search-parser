<?php

namespace RebeccaTheDev\SearchParser\Transforms;

use RebeccaTheDev\SearchParser\SearchQuery;
use RebeccaTheDev\SearchParser\SearchQueryComponent;

/**
 * An abstract class that defines a transform. You must extend this class to
 * write transforms.
 */
abstract class Transform implements TransformsComponents {

    /**
     * Holds any sub-transforms.
     *
     * @var array<TransformsComponents>
     */
    protected array $transforms = [];

    /**
     * The constructor with promoted properties.
     */
    public function __construct(
        protected ?string $defaultField = null,
        protected mixed $context = null,
        public bool $looseMode = false
    ) {}

    /**
     * Custom transformers must implement the transform method.
     */
    abstract public function transform(SearchQuery $query): mixed;

    /**
     * Adds a custom transform to the transforms list.
     */
    public function addComponentTransform(TransformsComponents $transform): void {
        $this->transforms[] = $transform;
    }
}
