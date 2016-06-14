<?php
/**
 * ConstraintBuilder class file
 */

namespace Graviton\SchemaBundle\Constraint;

use Graviton\RestBundle\Model\DocumentModel;
use Graviton\SchemaBundle\Constraint\Builder\ConstraintBuilderInterface;
use Graviton\SchemaBundle\Document\Schema;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class ConstraintBuilder
{
    /**
     * @var ConstraintBuilderInterface[]
     */
    private $builders = [];

    /**
     * Add constraint builder
     *
     * @param ConstraintBuilderInterface $builder Constraint builder
     *
     * @return void
     */
    public function addConstraintBuilder(ConstraintBuilderInterface $builder)
    {
        $this->builders[] = $builder;
    }

    /**
     * Go through the constraints and call the builders to do their job
     *
     * @param string        $fieldName field name
     * @param Schema        $property  the property
     * @param DocumentModel $model     the parent model
     *
     * @return Schema
     */
    public function addConstraints($fieldName, Schema $property, DocumentModel $model)
    {
        $constraints = $model->getConstraints($fieldName);

        if (!is_array($constraints)) {
            return $property;
        }

        foreach ($constraints as $constraint) {
            foreach ($this->builders as $builder) {
                if ($builder->supportsConstraint($constraint->name, $constraint->options)) {
                    $property = $builder->buildConstraint($fieldName, $property, $model, $constraint->options);
                }
            }
        }

        return $property;
    }
}