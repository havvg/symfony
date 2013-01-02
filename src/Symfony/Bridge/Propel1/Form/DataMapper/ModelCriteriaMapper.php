<?php

namespace Symfony\Bridge\Propel1\Form\DataMapper;

use ModelCriteria;

use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormInterface;

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

        if (!$data instanceof ModelCriteria) {
            throw new UnexpectedTypeException($data, 'ModelCriteria');
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

        if (!$data instanceof ModelCriteria) {
            throw new UnexpectedTypeException($data, 'ModelCriteria');
        }

        /* @var $eachForm \Symfony\Component\Form\FormInterface */
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
     * @param FormInterface $form  The filter form containing the property path and the data.
     * @param ModelCriteria $query The query object to join the property path onto.
     * @param int           $index The index of the property path being traversed.
     *
     * @throws \RuntimeException If the relation path is invalid.
     */
    protected function usePath(FormInterface $form, ModelCriteria $query, $index = 0)
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
            throw new \RuntimeException(sprintf('The relation between %s and %s does not exist.', $query->getModelName(), $relation));
        }

        /* @var $useQuery ModelCriteria */
        $useQuery = $query->{'use'.$relation.'Query'}();
        $this->usePath($form, $useQuery, ++$index);
        $useQuery->endUse();
    }
}
