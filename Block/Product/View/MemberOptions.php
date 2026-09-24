<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Block\Product\View;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class MemberOptions extends Template
{
    public function __construct(
        Context $context,
        private Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCurrentProduct(): ?Product
    {
        return $this->registry->registry('product');
    }

    public function hasSpouseOption(): bool
    {
        $product = $this->getCurrentProduct();
        return $product ? (bool) $product->getData('has_spouse') : false;
    }

    public function hasChildOption(): bool
    {
        $product = $this->getCurrentProduct();
        return $product ? (bool) $product->getData('has_child') : false;
    }

    public function shouldRender(): bool
    {
        return $this->hasSpouseOption() || $this->hasChildOption();
    }
}
