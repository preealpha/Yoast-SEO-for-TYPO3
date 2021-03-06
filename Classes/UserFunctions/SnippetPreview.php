<?php
namespace YoastSeoForTypo3\YoastSeo\UserFunctions;

use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class SnippetPreview
{
    public function render()
    {
        $title = $body = $metaDescription = '';
        $uriToCheck = urldecode($GLOBALS['TYPO3_REQUEST']->getQueryParams()['uriToCheck']);

        /** @var SiteLanguage $siteLanguage */
        $siteLanguage = $GLOBALS['TYPO3_REQUEST']->getAttribute('language');
        $content = $this->getContentFromUrl($uriToCheck);

        $titleFound = preg_match("/<title[^>]*>(.*?)<\/title>/is", $content, $matchesTitle);
        $descriptionFound = preg_match("/<title[^>]*>(.*?)<\/title>/is", $content, $matchesDescription);
        $bodyFound = preg_match("/<body[^>]*>(.*?)<\/body>/is", $content, $matchesBody);

        if ($bodyFound) {
            $body = $matchesBody[1];

            preg_match_all(
                '/<!--\s*?TYPO3SEARCH_begin\s*?-->.*?<!--\s*?TYPO3SEARCH_end\s*?-->/mis',
                $body,
                $indexableContents
            );

            if (is_array($indexableContents[0]) && !empty($indexableContents[0])) {
                $body = implode($indexableContents[0], '');
            }
        }

        if ($titleFound) {
            $title = $matchesTitle[1];
        }

        if ($descriptionFound) {
            $metaDescription = $matchesDescription[1];
        }

        $url = preg_replace('/\/$/', '', $uriToCheck);

        $titlePrependAppend = $this->getPageTitlePrependAppend();
        if ($content !== null) {
            $data = [
                'id' => $GLOBALS['TSFE']->id,
                'url' => $url,
                'baseUrl' => preg_replace('/' . preg_quote($GLOBALS['TSFE']->page['slug'], '/') . '$/', '', $url),
                'slug' => $GLOBALS['TSFE']->page['slug'],
                'title' => $title,
                'description' => $metaDescription,
                'locale' => $siteLanguage->getLocale(),
                'body' => $body,
                'pageTitlePrepend' => $titlePrependAppend['prepend'],
                'pageTitleAppend' => $titlePrependAppend['append'],
            ];
        } else {
            $data = ['error' => 'Could not read the url ' . $uriToCheck];
        }

        return json_encode($data);
    }

    /**
     * @param $uriToCheck
     * @return null|string
     */
    protected function getContentFromUrl($uriToCheck)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uriToCheck);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200) {
            curl_close($ch);
            return (string)$result;
        } else {
            throw new Exception(curl_error($ch));
            curl_close($ch);
            return null;
        }
    }

    protected function getPageTitlePrependAppend(): array
    {
        $prependAppend = ['prepend' => '', 'append' => ''];
        $siteTitle = trim($GLOBALS['TSFE']->tmpl->setup['sitetitle'] ?? '');
        $pageTitleFirst = (bool)($GLOBALS['TSFE']->config['config']['pageTitleFirst'] ?? false);
        $pageTitleSeparator = $this->getPageTitleSeparator();
        // only show a separator if there are both site title and page title
        if ($siteTitle === '') {
            $pageTitleSeparator = '';
        } elseif (empty($pageTitleSeparator)) {
            // use the default separator if non given
            $pageTitleSeparator = ': ';
        }

        if ($pageTitleFirst) {
            $prependAppend['append'] = $pageTitleSeparator . $siteTitle;
        } else {
            $prependAppend['prepend'] = $siteTitle . $pageTitleSeparator;
        }

        return $prependAppend;
    }

    protected function getPageTitleSeparator()
    {
        $pageTitleSeparator = '';
        // Check for a custom pageTitleSeparator, and perform stdWrap on it
        if (isset($GLOBALS['TSFE']->config['config']['pageTitleSeparator']) && $GLOBALS['TSFE']->config['config']['pageTitleSeparator'] !== '') {
            $pageTitleSeparator = $GLOBALS['TSFE']->config['config']['pageTitleSeparator'];

            if (isset($GLOBALS['TSFE']->config['config']['pageTitleSeparator.']) && is_array($GLOBALS['TSFE']->config['config']['pageTitleSeparator.'])) {
                $pageTitleSeparator = $GLOBALS['TSFE']->cObj->stdWrap($pageTitleSeparator, $GLOBALS['TSFE']->config['config']['pageTitleSeparator.']);
            } else {
                $pageTitleSeparator .= ' ';
            }
        }

        return $pageTitleSeparator;
    }
}
