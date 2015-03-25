<?php
namespace O3Co\Query\Bridge\DoctrineOrm\Visitor;

use O3Co\Query\Bridge\DoctrineOrm\Query;
use O3Co\Query\Bridge\DoctrineOrm;;
use O3Co\Query\Query\Term;
use O3Co\Query\Query\Visitor;
use O3Co\Query\Query\Visitor\ExpressionVisitor as BaseVisitor;
use O3Co\Query\Query\Visitor\OutputVisitor,
    O3Co\Query\Query\Visitor\FieldResolver;
use Doctrine\ORM\EntityManager as DoctrineEntityManager;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Query\Expr as DoctrineOrmExpr;

/**
 * ExpressionVisitor 
 *   Convert Expression to DoctrineQUery 
 * @uses Visitor
 * @package { PACKAGE }
 * @copyright Copyrights (c) 1o1.co.jp, All Rights Reserved.
 * @author Yoshi<yoshi@1o1.co.jp> 
 * @license { LICENSE }
 */
class ExpressionVisitor extends BaseVisitor implements Visitor 
{
	private $em;

	private $classMetadata;

    private $queryBuilder;

	/**
	 * relationalFields 
	 *   map relational tables for field.
	 *   ex)
	 *     "owner.id" meens "field id on relational owner"
	 *     
	 * @var mixed
	 * @access private
	 */
	private $fieldResolver;

	public function __construct(DoctrineEntityManager $em, $class)
	{
		$this->em = $em;
		$this->classMetadata = $em->getClassMetadata($class);

        // 
        $this->fieldResolver = new FieldResolver\SequentialFieldResolver(array(
                new DoctrineOrm\Visitor\FieldResolver\MappedRelationalFieldResolver($this->classMetadata), 
                new DoctrineOrm\Visitor\FieldResolver\RootAliasFieldResolver(), 
            ));
	}

    public function reset()
    {
        $this->queryBuilder = null;
        $this->getFieldResolver()->reset();
    }

	public function visitStatement(Term\Statement $statement)
	{
        $this->reset();
        $qb = $this->getQueryBuilder();

		// apply
		$this->visitConditionalClause($statement->getClause('condition'));
		//$this->visitOrderClause($statement->getClause('order'));
	}

	public function visitConditionalClause(Term\ConditionalClause $clause)
	{
        $qb = $this->getQueryBuilder();
		foreach($clause->getTerms() as $term) {
			$qb->andWhere($term->dispatch($this));
		}
	}

	public function visitOrderClause(Term\OrderClause $clause)
	{

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
     * @param Term\LogicalExpression $expr 
     * @access public
     * @return void
     */
    public function visitLogicalExpression(Term\LogicalExpression $expr)
    {
        foreach($expr->getExpressions() as $innerExpr) {
            $exprs[] = $this->visit($innerExpr);
        }

        $qb = $this->getQueryBuilder();
        switch($expr->getType()) {
        case Term\LogicalExpression::TYPE_AND:
            return new DoctrineOrmExpr\Andx($exprs);
        case Term\LogicalExpression::TYPE_OR:
            return new DoctrineOrmExpr\Orx($exprs);
        case Term\LogicalExpression::TYPE_NOT:
            return new DoctrineOrmExpr\Func('NOT', $exprs);
        default:
            throw new \RuntimeException(sprintf('Unknown type of LogicalExpression operator: [%s]', (string)$expr->getType()));
        }
    }

    public function visitComparisonExpression(Term\ComparisonExpression $expr) 
    {
        // fixme
        $rawValue = $this->visitValueExpression($expr->getValue());
        $parameterName = str_replace('.', '_', $expr->getField()) . '_' . substr(md5($rawValue), 0, 5);
        $parameter = new Parameter($parameterName, $rawValue);
        $placeHolder = ':' . $parameterName;

        $qb = $this->getQueryBuilder();
        switch($expr->getOperator()) {
        case Term\ComparisonExpression::EQ:
            if(null === $rawValue) {
                return $qb->expr()->isNull($this->visitField($expr->getField()));
            }
            $qb->getParameters()->add($parameter);
            return $qb->expr()->eq($this->visitField($expr->getField()), $placeHolder);
        case Term\ComparisonExpression::NEQ:
            if(null === $rawValue) {
                return $qb->expr()->isNotNull($this->visitField($expr->getField()));
            }
            $qb->getParameters()->add($parameter);
            return $qb->expr()->neq($this->visitField($expr->getField()), $placeHolder);
        case Term\ComparisonExpression::GT:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->gt($this->visitField($expr->getField()), $placeHolder);
        case Term\ComparisonExpression::GTE:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->gte($this->visitField($expr->getField()), $placeHolder);
        case Term\ComparisonExpression::LT:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->lt($this->visitField($expr->getField()), $placeHolder);
        case Term\ComparisonExpression::LTE:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->lte($this->visitField($expr->getField()), $placeHolder);
            break;
        default:
            throw new \RuntimeException(sprintf('Unknown Operator[%s] for ComparisonExpression.', (string)$textComparison->getOperator()));
            break;
        }
    }

    public function visitTextComparisonExpression(Term\TextComparisonExpression $textComparison)
    {
        // fixme
        $rawValue = $this->visitValueExpression($textComparison->getValue());
        $parameterName = str_replace('.', '_', $textComparison->getField()) . '_' . substr(md5($rawValue), 0, 5);
        $placeHolder = ':' . $parameterName;

        $qb = $this->getQueryBuilder();
        switch($textComparison->getOperator()) {
        case Term\TextComparisonExpression::MATCH:
            $parameter = new Parameter($parameterName, $this->convertWildcardValue($rawValue));
            $qb->getParameters()->add($parameter);
            return $qb->expr()->like($this->visitField($textComparison->getField()), $placeHolder);
        case Term\TextComparisonExpression::NOT_MATCH:
            $parameter = new Parameter($parameterName, $this->convertWildcardValue($rawValue));
            $qb->getParameters()->add($parameter);
            return $qb->expr()->notLike($this->visitField($textComparison->getField()), $placeHolder);
        case Term\TextComparisonExpression::CONTAIN:
            $parameter = new Parameter($parameterName, '%' . $rawValue . '%');
            $qb->getParameters()->add($parameter);
            return $qb->expr()->like($this->visitField($textComparison->getField()), $placeHolder);
        case Term\TextComparisonExpression::NOT_CONTAIN:
            $parameter = new Parameter($parameterName, '%' . $rawValue . '%');
            $qb->getParameters()->add($parameter);
            return $qb->expr()->notLike($this->visitField($textComparison->getField()), $placeHolder);
        default:
            throw new \RuntimeException(sprintf('Unknown Operator[%s] for TextComparisonExpression.', (string)$textComparison->getOperator()));
        }
    }

    public function visitCollectionComparisonExpression(Term\CollectionComparisonExpression $comparison)
    {
        // fixme
        $rawValue = $this->visitValueExpression($comparison->getValue());
        $parameterName = str_replace('.', '_', $comparison->getField()) . '_' . substr(md5(json_encode($rawValue)), 0, 5);
        $parameter = new Parameter($parameterName, $rawValue);
        $placeHolder = ':' . $parameterName;

        $qb = $this->getQueryBuilder();
        switch($comparison->getOperator()) {
        case Term\CollectionComparisonExpression::IN:
            $qb->getParameters()->add($parameter);
            return $qb->expr()->in($this->visitField($comparison->getField()), $placeHolder);
        case Term\CollectionComparisonExpression::NOT_IN:
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
     * visitValueExpression 
     * 
     * @param Term\ValueExpression $expr 
     * @access public
     * @return void
     */
    public function visitValueExpression(Term\ValueExpression $expr)
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

