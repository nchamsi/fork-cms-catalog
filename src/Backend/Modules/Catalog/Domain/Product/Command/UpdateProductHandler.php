<?php

namespace Backend\Modules\Catalog\Domain\Product\Command;

use Backend\Core\Engine\Model;
use Backend\Modules\Catalog\Domain\Product\Product;
use Backend\Modules\Catalog\Domain\Product\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;


final class UpdateProductHandler
{
    /** @var ProductRepository */
    private $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function handle(UpdateProduct $updateProduct): void
    {
        $product = Product::fromDataTransferObject($updateProduct);

        // remove the specification values
        foreach ($updateProduct->remove_specification_values as $specification_value) {
            $product->removeSpecificationValue($specification_value);
        }

        // set the new product entity
        foreach ($updateProduct->specials as $special) {
            $special->setProduct($product);
        }

        // remove specials
        $entityManager = Model::get('doctrine.orm.entity_manager');
        foreach ($updateProduct->remove_specials as $special) {
            $entityManager->remove($special);
        }

        // first update all the relating product
        foreach ($updateProduct->related_products as $related_product) {
            // cleanup current data
            $related_product->removeRelatedProduct($product);

            // add new related products
            $related_product->addRelatedProduct($product);
        }

        // second remove all the other related products
        foreach ($updateProduct->remove_related_products as $related_product) {
            $related_product->removeRelatedProduct($product);
        }

        $entityManager->flush();


        // store the product
        $this->productRepository->add($product);

        $updateProduct->setProductEntity($product);
    }
}
