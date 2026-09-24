<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Adds the "Reveal" row action. Clicking it only opens the password-prompt
 * modal client-side (see reveal-sensitive.js) -- no sensitive data is
 * fetched until the admin re-enters their password and the server issues a
 * scoped token, per Req. 8/9.
 */
class RevealAction extends Column
{
    private const URL_PATH_VERIFY = 'memberinfo/memberinfo/verifypassword';
    private const URL_PATH_REVEAL = 'memberinfo/memberinfo/reveal';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $item[$this->getData('name')] = [
                'reveal' => [
                    // No 'href': Magento_Ui/js/grid/columns/actions.isHandlerRequired()
                    // only attaches a JS click handler when action.href is falsy (or
                    // callback/confirm is set) -- a truthy href like '#' makes it treat
                    // this as a plain link and skip wiring defaultCallback entirely.
                    'label' => __('Reveal'),
                    'row_id' => $item['order_item_id'],
                    'verify_url' => $this->urlBuilder->getUrl(self::URL_PATH_VERIFY),
                    'reveal_url' => $this->urlBuilder->getUrl(self::URL_PATH_REVEAL),
                ],
            ];
        }

        return $dataSource;
    }
}
