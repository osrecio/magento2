<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\ConfigurableProduct\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\ConfigurableProduct\Api\OptionRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\ConfigurableFactory;

/**
 * Class SaveHandler
 */
class SaveHandler
{
    /**
     * @var OptionRepositoryInterface
     */
    private $optionRepository;

    /**
     * @var ConfigurableFactory
     */
    private $configurableFactory;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $productAttributeRepository;

    /**
     * SaveHandler constructor
     *
     * @param OptionRepositoryInterface $optionRepository
     * @param ConfigurableFactory $configurableFactory
     * @param ProductAttributeRepositoryInterface $productAttributeRepository
     */
    public function __construct(
        OptionRepositoryInterface $optionRepository,
        ConfigurableFactory $configurableFactory,
        ProductAttributeRepositoryInterface $productAttributeRepository
    ) {
        $this->optionRepository = $optionRepository;
        $this->configurableFactory = $configurableFactory;
        $this->productAttributeRepository = $productAttributeRepository;
    }

    /**
     * @param string $entityType
     * @param ProductInterface $entity
     * @return ProductInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute($entityType, ProductInterface $entity)
    {
        if ($entity->getTypeId() !== Configurable::TYPE_CODE) {
            return $entity;
        }

        $extensionAttributes = $entity->getExtensionAttributes();
        if ($extensionAttributes === null) {
            return $entity;
        }

        $ids = [];
        $configurableOptions = $extensionAttributes->getConfigurableProductOptions();
        if (!empty($configurableOptions)) {
            $ids = $this->saveConfigurableProductAttributes($entity, $configurableOptions);
        }

        $this->deleteConfigurableProductAttributes($entity, $ids);

        $configurableLinks = (array) $extensionAttributes->getConfigurableProductLinks();

        /** @var \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurable */
        $configurable = $this->configurableFactory->create();
        $configurable->saveProducts($entity, $configurableLinks);

        return $entity;
    }

    /**
     * Save attributes for configurable product
     *
     * @param ProductInterface $product
     * @param array $attributes
     * @return array
     */
    private function saveConfigurableProductAttributes(ProductInterface $product, array $attributes)
    {
        $ids = [];
        /** @var \Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute $attribute */
        foreach ($attributes as $attribute) {
            $eavAttribute = $this->productAttributeRepository->get($attribute->getAttributeId());
            $attribute->loadByProductAndAttribute($product, $eavAttribute);
            $ids[] = $this->optionRepository->save($product->getSku(), $attribute);
        }

        return $ids;
    }

    /**
     * Remove product attributes
     *
     * @param ProductInterface $product
     * @param array $attributesIds
     * @return void
     */
    private function deleteConfigurableProductAttributes(ProductInterface $product, array $attributesIds)
    {
        $list = $this->optionRepository->getList($product->getSku());
        foreach ($list as $item) {
            if (!in_array($item->getId(), $attributesIds)) {
                $this->optionRepository->deleteById($product->getSku(), $item->getId());
            }
        }
    }
}
