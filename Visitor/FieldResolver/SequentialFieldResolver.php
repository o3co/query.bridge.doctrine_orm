<?php
namespace O3Co\Query\Bridge\DoctrineOrm\Visitor\FieldResolver;

use O3Co\Query\Bridge\DoctrineOrm\Visitor\FieldResolver;

/**
 * SequentialFieldResolver 
 * 
 * @uses FieldResolver
 * @package \O3Co\Query
 * @copyright Copyrights (c) 1o1.co.jp, All Rights Reserved.
 * @author Yoshi<yoshi@1o1.co.jp> 
 * @license MIT
 */
class SequentialFieldResolver implements FieldResolver
{
    /**
     * resolvers 
     * 
     * @var array
     * @access private
     */
    private $resolvers = array();

    /**
     * __construct 
     * 
     * @param array $resolvers 
     * @access public
     * @return void
     */
    public function __construct(array $resolvers = array())
    {
        $this->resolvers = array();
        foreach($resolvers as $resolver) {
            $this->appendResolver($resolver);
        }
    }

    public function reset()
    {
        foreach($this->resolvers as $resolver) {
            $resolver->reset();
        }
    }

    /**
     * canResolveField 
     * 
     * @param mixed $field 
     * @access public
     * @return void
     */
    public function canResolveField($field) 
    {
        foreach($this->resolvers as $resolver) {
            if($resolver->canResolveField($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * getResolvers 
     * 
     * @access public
     * @return void
     */
    public function getResolvers()
    {
        return $this->resolvers;
    }

    /**
     * setResolvers 
     * 
     * @param array $resolvers 
     * @access public
     * @return void
     */
    public function setResolvers(array $resolvers)
    {
        $this->resolvers = array();
        foreach($resolvers as $resolver) {
            $this->appendResolver($resolver);
        }
    }

    /**
     * resolveField 
     * 
     * @param mixed $field 
     * @access public
     * @return void
     */
    public function resolveField($field, array $options = array())
    {
        foreach($this->resolvers as $resolver) {
            if($resolver->canResolveField($field)) {
                $field  = $resolver->resolveField($field, $options);
            }
        }

        return $field;
    }

    /**
     * prependResolver 
     * 
     * @param FieldResolver $resolver 
     * @access public
     * @return void
     */
    public function prependResolver(FieldResolver $resolver)
    {
        array_unshift($this->resolvers, $resolver);
        return $this;
    }

    /**
     * appendResolver 
     * 
     * @param FieldResolver $resolver 
     * @access public
     * @return void
     */
    public function appendResolver(FieldResolver $resolver)
    {
        array_push($this->resolvers, $resolver);
        return $this;
    }
}

