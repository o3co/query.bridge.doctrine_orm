<?php
namespace O3Co\Query\Bridge\DoctrineOrm\Visitor;

/**
 * FieldResolver 
 *   Resolve field name of Association.
 * 
 * @package \O3Co\Query
 * @copyright Copyrights (c) 1o1.co.jp, All Rights Reserved.
 * @author Yoshi<yoshi@1o1.co.jp> 
 * @license MIT
 */
interface FieldResolver
{
    /**
     * canResolveField 
     *    
     * @param string $field 
     * @access public
     * @return bool 
     */
    function canResolveField($field);

    /**
     * resolveField 
     * 
     * @param mixed $field 
     * @access public
     * @return void
     */
    function resolveField($field, array $options = array());
}
