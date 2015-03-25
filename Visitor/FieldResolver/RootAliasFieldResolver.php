<?php
namespace O3Co\Query\Bridge\DoctrineOrm\Visitor\FieldResolver;

use O3Co\Query\Query\Visitor\FieldResolver;
use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;

/**
 * RootAliasFieldResolver 
 *   FieldResolver to resolve field on rootAlias 
 * @uses FieldResolver
 * @package { PACKAGE }
 * @copyright Copyrights (c) 1o1.co.jp, All Rights Reserved.
 * @author Yoshi<yoshi@1o1.co.jp> 
 * @license { LICENSE }
 */
class RootAliasFieldResolver implements FieldResolver 
{
    public function reset()
    {
    }

    public function canResolveField($field)
    {
        // fixme: should be validate the field with classMetadata
        return false === strpos($field, '.');
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
        return $options['root_alias'] . '.' . $field;
	}
}

