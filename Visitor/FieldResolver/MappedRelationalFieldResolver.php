<?php
namespace O3Co\Query\Bridge\DoctrineOrm\Visitor\FieldResolver;

use O3Co\Query\Bridge\DoctrineOrm\Visitor\FieldResolver;
use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;

/**
 * MappedRelationalFieldResolver 
 *   FieldResolver to resolve relations with Doctrine Field Mapping 
 * @uses FieldResolver
 * @package { PACKAGE }
 * @copyright Copyrights (c) 1o1.co.jp, All Rights Reserved.
 * @author Yoshi<yoshi@1o1.co.jp> 
 * @license { LICENSE }
 */
class MappedRelationalFieldResolver implements FieldResolver 
{
	/**
	 * associations 
	 * 
	 * @var array
	 * @access private
	 */
	private $associations = array();

    private $classMetadata;


    public function __construct(DoctrineClassMetadata $classMetadata)
    {
        $this->classMetadata = $classMetadata;
    }

    public function reset()
    {
        $this->associations = array();
    }

    public function canResolveField($field)
    {
        // fixme: should be validate the field with classMetadata
        return false !== strpos($field, '.');
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
		$fieldParts = explode('.', $field);
		
		$association = null;
		$parts = array(); 
		while(2 <= count($fieldParts)) {
			$nestedField[] = array_shift($fieldParts);

			$association = $this->resolveRelationalAssociation($nestedField, $options);
		}

		if(!$association) {
			throw new \Exception('Association is not specified to resolve field. Unassociated field is not supported to resolve.');
		}

		return $association. '.' . array_shift($fieldParts);
	}

	/**
	 * resolveRelationalAssociation 
	 * 
	 * @param array $parts 
	 * @access protected
	 * @return void
	 */
	protected function resolveRelationalAssociation(array $parts, array $options)
	{
        $rootAlias = $options['root_alias'];
        $qb = $options['query_builder'];
		$association = implode('.', $parts);
		if(!isset($this->associations[$association])) {
			if(1 === count($parts)) {
				$parentAssociation = $rootAlias;
				$field = $association;
			} else {
				// last one is the next join field
				$field  = array_pop($parts); 
				// rest is the parent alias part and the parent field should be resolved before this called.
				$parentName = implode('.', $parts);
				if(!isset($this->associations[$parentName])) {
					throw new \RuntimeException(sprintf('Parent association "%s" has to be resolved before resolve association "%s"', $parentName, $association));
				}
				$parentAssociation = $this->associations[implode('.', $parts)];
			}

			// set next association number
			$alias = 't_' . count($this->associations);
			$qb->innerJoin(	
					$parentAssociation . '.' . $field,
					$alias
				);

			$this->associations[$association] = $alias;
		}

		return $this->associations[$association];
	}
    
    public function getClassMetadata()
    {
        return $this->classMetadata;
    }
    
    public function setClassMetadata($classMetadata)
    {
        $this->classMetadata = $classMetadata;
        return $this;
    }
}

