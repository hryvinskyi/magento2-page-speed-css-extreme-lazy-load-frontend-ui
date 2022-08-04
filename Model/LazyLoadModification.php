<?php
/**
 * Copyright (c) 2022. All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\PageSpeedCssExtremeLazyLoadFrontendUi\Model;

use Hryvinskyi\PageSpeedApi\Api\Finder\CssInterface as CssFinderInterface;
use Hryvinskyi\PageSpeedApi\Api\Finder\Result\TagInterface;
use Hryvinskyi\PageSpeedApi\Api\Html\ReplaceIntoHtmlInterface;
use Hryvinskyi\PageSpeedApi\Model\ModificationInterface;
use Hryvinskyi\PageSpeedCssExtremeLazyLoad\Api\ConfigInterface;
use Hryvinskyi\PageSpeedCssExtremeLazyLoad\Model\CanCssLazyLoadingInterface;
use Magento\Framework\App\RequestInterface;

class LazyLoadModification implements ModificationInterface
{
    private CssFinderInterface $cssFinder;
    private ReplaceIntoHtmlInterface $replaceIntoHtml;
    private ConfigInterface $config;
    private CanCssLazyLoadingInterface $canCssLazyLoading;
    private RequestInterface $request;

    /**
     * @param CssFinderInterface $cssFinder
     * @param ReplaceIntoHtmlInterface $replaceIntoHtml
     * @param ConfigInterface $config
     * @param CanCssLazyLoadingInterface $canCssLazyLoading
     */
    public function __construct(
        CssFinderInterface $cssFinder,
        ReplaceIntoHtmlInterface $replaceIntoHtml,
        ConfigInterface $config,
        CanCssLazyLoadingInterface $canCssLazyLoading,
        RequestInterface $request
    ) {
        $this->cssFinder = $cssFinder;
        $this->replaceIntoHtml = $replaceIntoHtml;
        $this->config = $config;
        $this->canCssLazyLoading = $canCssLazyLoading;
        $this->request = $request;
    }

    /**
     * @inheritdoc
     */
    public function execute(string &$html): void
    {
        if ($this->config->isEnabled() === false) {
            return;
        }

        if ($this->config->isApplyForPageTypes() === true
            && in_array($this->request->getFullActionName(), $this->config->getApplyForPageTypes(), true) === false) {
            return;
        }

        if ($this->config->isDisableForPageTypes() === true
            && in_array($this->request->getFullActionName(), $this->config->getDisableForPageTypes(), true) === true) {
            return;
        }

        $tagList = $this->cssFinder->findExternal($html);
        $replaceData = [];
        foreach ($tagList as $tag) {
            /** @var $tag TagInterface */
            if ($this->canCssLazyLoading->execute($tag) === false) {
                continue;
            }

            $replaceAttributes = [
                'rel' => 'lazyload-css',
                'href' => null
            ];

            if (isset($tag->getAttributes()['href'])) {
                $replaceAttributes['data-lazy-source'] = $tag->getAttributes()['href'];
            }

            $replaceData[] = [
                'start' => $tag->getStart(),
                'end' => $tag->getEnd(),
                'content' => $tag->getContentWithUpdatedAttribute($replaceAttributes),
            ];
        }

        foreach (array_reverse($replaceData) as $replaceElementData) {
            $html = $this->replaceIntoHtml->execute(
                $html,
                $replaceElementData['content'],
                $replaceElementData['start'],
                $replaceElementData['end']
            );
        }
    }
}
