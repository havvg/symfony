<?php

namespace Symfony\Bridge\Propel1\Form\DataMapper;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * A DataMapper mapping a ModelCriteria to a form type.
 *
 * @author Toni Uebernickel <tuebernickel@gmail.com>
 */
class ModelCriteriaMapper implements DataMapperInterface
{
    public function mapDataToForms($data, array $forms)
    {
        if (null === $data) {
            return;
        }

        if (!$data instanceof \ModelCriteria) {
            throw new UnexpectedTypeException($data, '\ModelCriteria');
        }

        /*
         * @todo Implement mapDataToForms to actually map current Criteria to the forms.
         *       The idea is to map the value and comparison to each form.
         *       A DataTransformer attached to the form will then transform this data into the view data of the form.
         *       E.g. a simple flag (boolean) of the model will be transformed to a choice containing both + "ignore" value.
         *
         *       **Important** For now the form has to be configured correctly to actually work the way it's expected!
         */
    }

    public function mapFormsToData(array $forms, &$data)
    {
        if (null === $data) {
            return;
        }

        if (!$data instanceof \ModelCriteria) {
            throw new UnexpectedTypeException($data, '\ModelCriteria');
        }

        /* @var $eachForm \Symfony\Component\Form\FormInterface */
        foreach ($forms as $eachForm) {
            // The string is a dot-path respresentation.
            $propertyPath = (string) $eachForm->getPropertyPath();

            // Skip those, we don't want!
            //    no path               mapped => false                     DataTransformer failed          disabled => true           no actual data
            if (!$propertyPath || !$eachForm->getConfig()->getMapped() || !$eachForm->isSynchronized() || $eachForm->isDisabled() || $eachForm->isEmpty()) {
                continue;
            }

            /*
             * This allows to use custom implementations for e.g. "virtual" columns.
             * It will also leverage generated methods in the base classes of the Query API.
             */
            if (method_exists($data, 'filterBy'.$propertyPath)) {
                call_user_func(array($data, 'filterBy'.$propertyPath), $eachForm->getData());

                continue;
            }

            /*
             * Check whether the property_path contains a relation for the current model.
             *
             * @todo Handle deeper relation paths.
             */
            $relation = $eachForm->getPropertyPath()->getElement(0);
            if ($data->getTableMap()->hasRelation($relation)) {
                $column = $eachForm->getPropertyPath()->getElement(1);

                $data
                    ->{'use'.$relation.'Query'}()
                        ->filterBy($column, $eachForm->getData())
                    ->endUse()
                ;

                continue;
            }

            $data->filterBy($propertyPath, $eachForm->getData());
        }
    }
}
