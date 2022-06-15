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
use Hryvinskyi\PageSpeedCssExtremeLazyLoad\Model\CanCssLazyLoadingInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class LazyLoadModification implements ModificationInterface
{
    private CssFinderInterface $cssFinder;
    private ReplaceIntoHtmlInterface $replaceIntoHtml;
    private ScopeConfigInterface $scopeConfig;
    private CanCssLazyLoadingInterface $canCssLazyLoading;

    /**
     * @param CssFinderInterface $cssFinder
     * @param ReplaceIntoHtmlInterface $replaceIntoHtml
     * @param ScopeConfigInterface $scopeConfig
     * @param CanCssLazyLoadingInterface $canCssLazyLoading
     */
    public function __construct(
        CssFinderInterface $cssFinder,
        ReplaceIntoHtmlInterface $replaceIntoHtml,
        ScopeConfigInterface $scopeConfig,
        CanCssLazyLoadingInterface $canCssLazyLoading
    ) {
        $this->cssFinder = $cssFinder;
        $this->replaceIntoHtml = $replaceIntoHtml;
        $this->scopeConfig = $scopeConfig;
        $this->canCssLazyLoading = $canCssLazyLoading;
    }

    /**
     * @inheritdoc
     */
    public function execute(string &$html): void
    {
        if ($this->scopeConfig->isSetFlag('hryvinskyi_pagespeed/css/extreme_lazy_load/enabled') === false) {
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
