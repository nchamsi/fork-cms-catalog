<?php

namespace Backend\Modules\Catalog\Domain\SpecificationValue;

use Backend\Core\Engine\Model;
use Common\Doctrine\Entity\Meta;
use Symfony\Component\Validator\Constraints as Assert;

class ProductSpecificationValueDataTransferObject
{
    public $specification;

    /**
     * @param SpecificationValue
     *
     * @Assert\NotBlank(message="err.FieldIsRequired")
     */
    public $value;

    public $product;

    public function __construct(SpecificationValue $specificationValue = null)
    {
        if (!$specificationValue) {
            return;
        }

        $this->specification = $specificationValue->getSpecification();
        $this->value         = $specificationValue;
    }

    public function getMeta(): Meta
    {
        $specificationValueRepository = Model::get('catalog.repository.specification_value');

        $meta = new Meta(
            '',
            false,
            '',
            false,
            $this->value->getValue(),
            false,
            $specificationValueRepository->getUrl($this->value->getValue(), $this->specification, null),
            false
        );

        return $meta;
    }
}
