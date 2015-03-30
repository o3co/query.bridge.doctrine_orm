<?php
namespace O3Co\Query\Bridge\DoctrineOrm;

use O3Co\Query\Query;
use O3Co\Query\Persister\AbstractPersister;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;

/**
 * DoctrineOrmPersister 
 * 
 * @uses AbstractPersister
 * @package { PACKAGE }
 * @copyright Copyrights (c) 1o1.co.jp, All Rights Reserved.
 * @author Yoshi<yoshi@1o1.co.jp> 
 * @license { LICENSE }
 */
class DoctrineOrmPersister extends AbstractPersister 
{
    /**
     * __construct 
     *  
     * @param EntityManager $em Doctrine EntityManager
     * @param string $class classname
     * @access public
     * @return void
     */
    public function __construct(EntityManager $em, $class)
    {
        if($class instanceof DoctrineClassMetadata) {
            $classMetadata = $class;
        } else {
            $classMetadata = $em->getClassMetadata($class);
        }

        $this->em = $em;
        $this->classMetadata = $classMetadata;

        parent::__construct(
                new Visitor\ExpressionVisitor($em, $classMetadata)
            );
    }

    /**
     * execute 
     * 
     * @param Query $query 
     * @access public
     * @return void
     */
    public function execute(Query $query)
    {
        $q = $this->getNativeQuery($query);

        return $q->execute();
    }
}

