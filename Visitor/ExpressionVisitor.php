<?php
namespace O3Co\Query\Bridge\DoctrineOrm\Visitor;

use O3Co\Query\Bridge\DoctrineOrm\Query;
use O3Co\Query\Bridge\DoctrineOrm;;
use O3Co\Query\Query\Expr;
use O3Co\Query\Query\Visitor\ExpressionVisitor as BaseVisitor;
use O3Co\Query\Query\Visitor\OuterVisitor;

use Doctrine\ORM\EntityManager as DoctrineEntityManager;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\Expr as DoctrineOrmExpr;
use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;

/**
 * ExpressionVisitor 
 *   Convert Expression to DoctrineQUery 
 * @uses OuterVisitor
 * @package \O3Co\Query
 * @copyright Copyrights (c) 1o1.co.jp, All Rights Reserved.
 * @author Yoshi<yoshi@1o1.co.jp> 
 * @license MIT
 */
class ExpressionVisitor extends BaseVisitor implements OuterVisitor 
{
    /**
     * em 
     * 
     * @var \Doctrine\ORM\EntityManager 
     * @access private
     */
    private $em;

    /**
     * classMetadata 
     * 
     * @var \Doctrine\ORM\Mapping\ClassMetadata 
     * @access private
     */
    private $classMetadata;

    /**
     * queryBuilder 
     * 
     * @var \Doctrine\ORM\QueryBuilder 
     * @access private
     */
    private $queryBuilder;

    /**
     * fieldResolver 
     *   FieldResolver is to resolve association and aliased field name.
     *     
     * @var mixed
     * @access private
     */
    private $fieldResolver;

    public function __construct(DoctrineEntityManager $em, $class)
    {
        $this->em = $em;

        if($class instanceof DoctrineClassMetadata) {
            $this->classMetadata = $class;
        } else {
            $this->classMetadata = $em->getClassMetadata($class);
        }

        // 
        $this->fieldResolver = new FieldResolver\SequentialFieldResolver(array(
                // resolve only field with association
                new DoctrineOrm\Visitor\FieldResolver\MappedRelationalFieldResolver($this->classMetadata), 
                // resolve only field on RootAlias
                new DoctrineOrm\Visitor\FieldResolver\RootAliasFieldResolver($this->classMetadata), 
            ));
    }

    public function reset()
    {
        $this->queryBuilder = null;
        $this->getFieldResolver()->reset();
    }

    public function visitStatement(Expr\Statement $statement)
    {
        $this->reset();
        $qb = $this->getQueryBuilder();

        // apply
        if($statement->hasClause('condition')) 
            $this->visitConditionalClause($statement->getClause('condition'));
        if($statement->hasClause('order'))
            $this->visitOrderClause($statement->getClause('order'));
        if($statement->hasClause('offset'))
            $this->visitOffsetClause($statement->getClause('offset'));
        if($statement->hasClause('limit'))
            $this->visitLimitClause($statement->getClause('limit'));
    }

    public function visitOffsetClause(Expr\OffsetClause $offset)
    {
        $this->getQueryBuilder()->setFirstResult($offset->getValue()->getValue());
    }

    public function visitLimitClause(Expr\LimitClause $limit)
    {
        $this->getQueryBuilder()->setMaxResults($limit->getValue()->getValue());
    }

    public function visitConditionalClause(Expr\ConditionalClause $clause)
    {
        $qb = $this->getQueryBuilder();
        $qb->andWhere($clause->getExpression()->dispatch($this));
    }

    public function visitOrderClause(Expr\OrderClause $clause)
    {
        $qb = $this->getQueryBuilder();
        foreach($clause->getExpressions() as $expr) {
            if($expr->isAsc()) {
                $qb->addOrderBy($this->visitField($expr->getField()), 'ASC');
            } else {
                $qb->addOrderBy($this->visitField($expr->getField()), 'DESC');
            }
        }
    }

    public function getQueryBuilder()
    {
        if(!$this->queryBuilder) {
            $this->queryBuilder = $this->em->createQueryBuilder();
            $this->queryBuilder
                ->select('root')
                ->from($this->getClassMetadata()->getName(), 'root')
            ;
        }
        return $this->queryBuilder;
    }

    public function getNativeQuery()
    {
        return $this->queryBuilder->getQuery();
    }
    
    /**
     * visitLogicalExpression 
     * 
     * @param Expr\LogicalExpression $expr 
     * @access public
     * @return void
     */
    public function visitLogicalExpression(Expr\LogicalExpression $expr)
    {
        foreach($expr->getExpressions() as $innerExpr) {
            $exprs[] = $this->visit($innerExpr);
        }

        $qb = $this->getQueryBuilder();
        switch($expr->getType()) {
        case Expr\LogicalExpression::TYPE_AND:
            return new DoctrineOrmExpr\Andx($exprs);
        case Expr\LogicalExpression::TYPE_OR:
            return new DoctrineOrmExpr\Orx($exprs);
        case Expr\LogicalExpression::TYPE_NOT:
            return new DoctrineOrmExpr\Func('NOT', $exprs);
        default:
            throw new \RuntimeException(sprintf('Unknown type of LogicalExpression operator: [%s]', (string)$expr->getType()));
        }
    }

    public function visitComparisonExpression(Expr\ComparisonExpression $expr) 
    {
        // fixme
        $rawValue = $this->visitValueIdentifier($expr->getValue());
        $parameterName = str_replace('.', '_', $expr->getField()) . '_' . substr(md5($rawValue), 0, 5);
        $parameter = new Parameter($parameterName, $rawValue);
        $placeHolder = ':' . $parameterName;

        $qb = $this->getQueryBuilder();
        switch($expr->getOperator()) {
        case Expr\ComparisonExpression::EQ:
            if(null === $rawValue) {
                return $qb->expr()->isNull($this->visitField($expr->getField()));
            }
            $qb->getParameters()->add($parameter);
            return $qb->expr()->eq($this->visitField($expr->getField()), $placeHolder);
        case Expr\ComparisonExpression::NEQ:
            if(null === $rawValue) {
                return $qb->expr()->isNotNull($this->visitField($expr->getField()));
            }
            $qb->getParameters()->add($parameter);
            return $qb->expr()->neq($this->visitField($expr->getField()), $placeHolder);
        case Expr\ComparisonExpression::GT:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->gt($this->visitField($expr->getField()), $placeHolder);
        case Expr\ComparisonExpression::GTE:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->gte($this->visitField($expr->getField()), $placeHolder);
        case Expr\ComparisonExpression::LT:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->lt($this->visitField($expr->getField()), $placeHolder);
        case Expr\ComparisonExpression::LTE:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->lte($this->visitField($expr->getField()), $placeHolder);
            break;
        default:
            throw new \RuntimeException(sprintf('Unknown Operator[%s] for ComparisonExpression.', (string)$textComparison->getOperator()));
            break;
        }
    }

    public function visitTextComparisonExpression(Expr\TextComparisonExpression $textComparison)
    {
        // fixme
        $rawValue = $this->visitValueIdentifier($textComparison->getValue());
        $parameterName = str_replace('.', '_', $textComparison->getField()) . '_' . substr(md5($rawValue), 0, 5);
        $placeHolder = ':' . $parameterName;

        $qb = $this->getQueryBuilder();
        switch($textComparison->getOperator()) {
        case Expr\TextComparisonExpression::MATCH:
            $parameter = new Parameter($parameterName, $this->convertWildcardValue($rawValue));
            $qb->getParameters()->add($parameter);
            return $qb->expr()->like($this->visitField($textComparison->getField()), $placeHolder);
        case Expr\TextComparisonExpression::NOT_MATCH:
            $parameter = new Parameter($parameterName, $this->convertWildcardValue($rawValue));
            $qb->getParameters()->add($parameter);
            return $qb->expr()->notLike($this->visitField($textComparison->getField()), $placeHolder);
        case Expr\TextComparisonExpression::CONTAIN:
            $parameter = new Parameter($parameterName, '%' . $rawValue . '%');
            $qb->getParameters()->add($parameter);
            return $qb->expr()->like($this->visitField($textComparison->getField()), $placeHolder);
        case Expr\TextComparisonExpression::NOT_CONTAIN:
            $parameter = new Parameter($parameterName, '%' . $rawValue . '%');
            $qb->getParameters()->add($parameter);
            return $qb->expr()->notLike($this->visitField($textComparison->getField()), $placeHolder);
        default:
            throw new \RuntimeException(sprintf('Unknown Operator[%s] for TextComparisonExpression.', (string)$textComparison->getOperator()));
        }
    }

    public function visitCollectionComparisonExpression(Expr\CollectionComparisonExpression $comparison)
    {
        // fixme
        $rawValue = $this->visitValueIdentifier($comparison->getValue());
        $parameterName = str_replace('.', '_', $comparison->getField()) . '_' . substr(md5(json_encode($rawValue)), 0, 5);
        $parameter = new Parameter($parameterName, $rawValue);
        $placeHolder = ':' . $parameterName;

        $qb = $this->getQueryBuilder();
        switch($comparison->getOperator()) {
        case Expr\CollectionComparisonExpression::IN:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->in($this->visitField($comparison->getField()), $placeHolder);
        case Expr\CollectionComparisonExpression::NOT_IN:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->notIn($this->visitField($comparison->getField()), $placeHolder);
        default:
            throw new \RuntimeException();
        }
    }

    /**
     * visitField 
     * 
     * @param mixed $field 
     * @access public
     * @return void
     */
    public function visitField($field)
    {
        return $this->getFieldResolver()->resolveField($field, array('root_alias' => $this->getQueryBuilder()->getRootAlias(), 'query_builder' => $this->getQueryBuilder()));
    }

    /**
     * visitValueIdentifier 
     * 
     * @param Expr\ValueIdentifier $expr 
     * @access public
     * @return void
     */
    public function visitValueIdentifier(Expr\ValueIdentifier $expr)
    {
        return $expr->getValue();
    }

    protected function convertWildcardValue($value)
    {
        for($i = 0; $i < strlen($value); $i++) {
            if(('*' == $value[$i]) || ('.' == $value[$i])) {
                if((0 < $i) && ('\\' == $value[$i-1])) {
                    continue;
                }
                // 
                $value[$i] = ('.' == $value[$i]) ? '_' : '%';
            }
        }
        return $value;
    }

    public function setFieldResolver(FieldResolver $resolver)
    {
        $this->fieldResolver = $resolver;
    }

    public function getFieldResolver()
    {
        if(!$this->fieldResolver) {
            $this->fieldResolver = new MappedAssociateFieldResovler($this->queryBuilder);
        }
        return $this->fieldResolver;
    }

    public function getEntityManager()
    {
        return $this->em;
    }
    
    public function getClassMetadata()
    {
        return $this->classMetadata;
    }
}

