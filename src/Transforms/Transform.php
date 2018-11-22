<?php

namespace peckrob\SearchParser\Transforms;

use peckrob\SearchParser\SearchQuery;
use peckrob\SearchParser\SearchQueryComponent;

/**
 * An abstract class that defines a transform. You must extend this class to 
 * write transforms.
 */
abstract class Transform implements TransformsComponents {

    /**
     * Loose mode will treat every text query as a LIKE query.
     *
     * @var boolean
     */
    public $looseMode = false;

    /**
     * Holds any sub-transforms.
     *
     * @var array
     */
    protected $transforms = [];

    /**
     * Holds the default field 
     *
     * @var string
     */
    protected $defaultField = null;

    /**
     * An optional object context that can be passed to the parser.
     *
     * @var object
     */
    protected $context = null;

    /**
     * The constructor.
     *
     * @param string $default_field The default field for text queries.
     * @param object $context       An optional object context.
     */
    public function __construct(string $default_field = null, $context = null) {
        $this->defaultField = $default_field;
        $this->context = $context;
    }

    /**
     * Custom transformers must implement the transform method.
     *
     * @param SearchQuery $query    An instance of SearchQuery.
     * @return mixed
     */
    abstract public function transform(SearchQuery $query);

    /**
     * Adds a custom transform to the 
     *
     * @param Transform $transform
     * @return void
     */
    public function addComponentTransform(TransformsComponents $transform) {
        $this->transforms[] = $transform;
    }
}
