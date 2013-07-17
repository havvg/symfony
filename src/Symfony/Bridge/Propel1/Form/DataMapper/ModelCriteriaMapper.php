<?php

namespace Symfony\Bridge\Propel1\Form\DataMapper;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormInterface;

use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * A DataMapper mapping a ModelCriteria to a form type.
 *
 * @author Toni Uebernickel <tuebernickel@gmail.com>
 */
class ModelCriteriaMapper implements DataMapperInterface
{
    public function mapDataToForms($data, $forms)
    {
        if (null === $data) {
            return;
        }

        if (!$data instanceof \ModelCriteria) {
            throw new UnexpectedTypeException($data, 'ModelCriteria');
        }

        /**
         * The list of applied criterions.
         *
         * The keys are translated to the phpName of the columns, which are used as property path values on the form.
         *
         * @var $filters \Criterion[]
         */
        $filters = array();

        /* @var $eachCriterion \Criterion */
        foreach ($data->getMap() as $eachTableColumn => $eachCriterion) {
            $path = new PropertyPath($eachTableColumn);

            /*
             * In case this is a related model. Currently not supporting many-to-many relations!
             *
             * @todo Implement path finding of many-to-many relations.
             *       This allows the use of X on the query for model Y, where Y and X are in a many-to-many relationship.
             *       While using the "filterByX" method of the query, the cross-table is defined in the Criterion, not the table of X.
             */
            if ($path->getElement(0) !== $data->getTableMap()->getName()) {
                /*
                 * The camelize is "default" behavior, but the actual phpName may vary.
                 *
                 * @todo Implement discovery of actual phpName by checking all relations on this model.
                 */
                $relationName = ucfirst(str_replace(" ", "", ucwords(strtr($path->getElement(0), "_-", "  "))));
                if (!$data->getTableMap()->hasRelation($relationName)) {
                    continue;
                }

                $peer = $data->getTableMap()->getRelation($relationName)->getForeignTable()->getPeerClassname();
                $key = $relationName.'.'.$this->translateFieldname($peer, $path->getElement(1));
            } else {
                $key = $this->translateFieldname($data->getModelPeerName(), $path->getElement(1));
            }

            $filters[$key] = $eachCriterion;
        }

        /* @var $eachForm FormInterface */
        foreach ($forms as $eachForm) {
            // Skip those we don't want!
            //    no path                                            mapped => false
            if (!$eachForm->getPropertyPath()->getLength() || !$eachForm->getConfig()->getMapped()) {
                continue;
            }

            $propertyPath = (string) $eachForm->getPropertyPath();
            if (empty($filters[$propertyPath])) {
                continue;
            }

            $criterion = $filters[$propertyPath];
            switch ($criterion->getComparison()) {
                /*
                 * This simply sets the value of the Criterion as value of the form.
                 *
                 * @todo Implement data setter based on comparison defined in the Criterion.
                 */
                case \Criteria::EQUAL:
                default:
                    $eachForm->setData($criterion->getValue());
            }
        }
    }

    public function mapFormsToData($forms, &$data)
    {
        if (null === $data) {
            return;
        }

        if (!$data instanceof \ModelCriteria) {
            throw new UnexpectedTypeException($data, 'ModelCriteria');
        }

        /* @var $eachForm FormInterface */
        foreach ($forms as $eachForm) {
            // Skip those, we don't want!
            //    no path                                            mapped => false                     DataTransformer failed          disabled => true           no actual data
            if (!$eachForm->getPropertyPath()->getLength() || !$eachForm->getConfig()->getMapped() || !$eachForm->isSynchronized() || $eachForm->isDisabled() || $eachForm->isEmpty()) {
                continue;
            }

            $this->usePath($eachForm, $data);
        }
    }

    /**
     * Recursively traverse the property path and chain the relations.
     *
     * In case the property-path contains more than one item, it's a dot-path notation of the related models.
     * The path will be traversed and the uses applied to the respective relation in the path.
     *
     * @param FormInterface  $form  The filter form containing the property path and the data.
     * @param \ModelCriteria $query The query object to join the property path onto.
     * @param int            $index The index of the property path being traversed.
     *
     * @throws \RuntimeException If the relation path is invalid.
     */
    protected function usePath(FormInterface $form, \ModelCriteria $query, $index = 0)
    {
        // The last property has been reached, which is the column.
        if ($index === $form->getPropertyPath()->getLength() - 1) {
            $column = $form->getPropertyPath()->getElement($index);

            /*
             * This allows to use custom implementations for e.g. "virtual" columns.
             * It will also leverage generated methods in the base classes of the Query API.
             */
            if (method_exists($query, 'filterBy'.$column)) {
                call_user_func(array($query, 'filterBy'.$column), $form->getData());
            } else {
                $query->filterBy($column, $form->getData());
            }

            return;
        }

        $relation = $form->getPropertyPath()->getElement($index);
        if (!$query->getTableMap()->hasRelation($relation)) {
            throw new \RuntimeException(sprintf('The relation between "%s" and "%s" does not exist.', $query->getModelName(), $relation));
        }

        /* @var $useQuery \ModelCriteria */
        $useQuery = $query->{'use'.$relation.'Query'}();
        $this->usePath($form, $useQuery, ++$index);
        $useQuery->endUse();
    }

    /**
     * Translate the fields name from "fieldName" to "phpName".
     *
     * @param string $peer  The FQCN of the peer class to translate the fieldName with.
     * @param string $field The name of the field to translate.
     *
     * @return string
     */
    protected function translateFieldname($peer, $field)
    {
        return call_user_func_array(array($peer, 'translateFieldName'), array($field, \BasePeer::TYPE_FIELDNAME, \BasePeer::TYPE_PHPNAME));
    }
}
