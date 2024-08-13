<?php

namespace Mautic\PageBundle\EventListener;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Form\Type\GatedVideoType;
use Mautic\CoreBundle\Form\Type\SlotButtonType;
use Mautic\CoreBundle\Form\Type\SlotCategoryListType;
use Mautic\CoreBundle\Form\Type\SlotChannelFrequencyType;
use Mautic\CoreBundle\Form\Type\SlotCodeModeType;
use Mautic\CoreBundle\Form\Type\SlotDwcType;
use Mautic\CoreBundle\Form\Type\SlotImageCaptionType;
use Mautic\CoreBundle\Form\Type\SlotImageCardType;
use Mautic\CoreBundle\Form\Type\SlotImageType;
use Mautic\CoreBundle\Form\Type\SlotPreferredChannelType;
use Mautic\CoreBundle\Form\Type\SlotSavePrefsButtonType;
use Mautic\CoreBundle\Form\Type\SlotSegmentListType;
use Mautic\CoreBundle\Form\Type\SlotSeparatorType;
use Mautic\CoreBundle\Form\Type\SlotSocialFollowType;
use Mautic\CoreBundle\Form\Type\SlotSocialShareType;
use Mautic\CoreBundle\Form\Type\SlotSuccessMessageType;
use Mautic\CoreBundle\Form\Type\SlotTextType;
use Mautic\CoreBundle\Helper\BuilderTokenHelperFactory;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\PageBundle\Entity\Page;
use Mautic\PageBundle\Event as Events;
use Mautic\PageBundle\Helper\TokenHelper;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PageBundle\PageEvents;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class BuilderSubscriber implements EventSubscriberInterface
{
    private const pageTokenRegex         = '{pagelink=(.*?)}';

    private const dwcTokenRegex          = '{dwc=(.*?)}';

    private const langBarRegex           = '{langbar}';

    private const shareButtonsRegex      = '{sharebuttons}';

    private const titleRegex             = '{pagetitle}';

    private const descriptionRegex       = '{pagemetadescription}';

    public const segmentListRegex         = '{segmentlist}';

    public const categoryListRegex        = '{categorylist}';

    public const channelfrequency         = '{channelfrequency}';

    public const preferredchannel         = '{preferredchannel}';

    public const saveprefsRegex           = '{saveprefsbutton}';

    public const successmessage           = '{successmessage}';

    public const identifierToken          = '{leadidentifier}';

    public const saveButtonContainerClass = 'prefs-saveprefs';

    public const firstSlotAttribute       = ' data-prefs-center-first="1"';

    /**
     * @var array<string,string>
     */
    private array $renderedContentCache = [];

    public function __construct(
        private CorePermissions $security,
        private TokenHelper $tokenHelper,
        private IntegrationHelper $integrationHelper,
        private PageModel $pageModel,
        private BuilderTokenHelperFactory $builderTokenHelperFactory,
        private TranslatorInterface $translator,
        private Connection $connection,
        private Environment $twig
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PageEvents::PAGE_ON_DISPLAY   => ['onPageDisplay', 0],
            PageEvents::PAGE_ON_BUILD     => ['onPageBuild', 0],
            EmailEvents::EMAIL_ON_BUILD   => ['onEmailBuild', 0],
            EmailEvents::EMAIL_ON_SEND    => ['onEmailGenerate', 0],
            EmailEvents::EMAIL_ON_DISPLAY => ['onEmailGenerate', 0],
        ];
    }

    public function onEmailBuild(EmailBuilderEvent $event): void
    {
        if ($event->tokensRequested([static::pageTokenRegex])) {
            $tokenHelper = $this->builderTokenHelperFactory->getBuilderTokenHelper('page');
            $event->addTokensFromHelper($tokenHelper, static::pageTokenRegex, 'title', 'id', true);
        }
    }

    public function onEmailGenerate(EmailSendEvent $event): void
    {
        $content      = $event->getContent();
        $plainText    = $event->getPlainText();
        $clickthrough = $event->shouldAppendClickthrough() ? $event->generateClickthrough() : [];
        $tokens       = $this->tokenHelper->findPageTokens($content.$plainText, $clickthrough);

        $event->addTokens($tokens);
    }

    /**
     * Add forms to available page tokens.
     */
    public function onPageBuild(Events\PageBuilderEvent $event): void
    {
        $tokenHelper = $this->builderTokenHelperFactory->getBuilderTokenHelper('page');

        if ($event->abTestWinnerCriteriaRequested()) {
            // add AB Test Winner Criteria
            $bounceRate = [
                'group'    => 'mautic.page.abtest.criteria',
                'label'    => 'mautic.page.abtest.criteria.bounce',
                'event'    => PageEvents::ON_DETERMINE_BOUNCE_RATE_WINNER,
            ];
            $event->addAbTestWinnerCriteria('page.bouncerate', $bounceRate);

            $dwellTime = [
                'group'    => 'mautic.page.abtest.criteria',
                'label'    => 'mautic.page.abtest.criteria.dwelltime',
                'event'    => PageEvents::ON_DETERMINE_DWELL_TIME_WINNER,
            ];
            $event->addAbTestWinnerCriteria('page.dwelltime', $dwellTime);
        }

        if ($event->tokensRequested([static::pageTokenRegex, static::dwcTokenRegex])) {
            $event->addTokensFromHelper($tokenHelper, static::pageTokenRegex, 'title', 'id', true);

            // add only filter based dwc tokens
            $dwcTokenHelper = $this->builderTokenHelperFactory->getBuilderTokenHelper('dynamicContent', 'dynamiccontent:dynamiccontents');
            $expr           = $this->connection->createExpressionBuilder()->and('e.is_campaign_based <> 1 and e.slot_name is not null');
            $tokens         = $dwcTokenHelper->getTokens(
                static::dwcTokenRegex,
                '',
                'name',
                'slot_name',
                $expr
            );
            $event->addTokens(is_array($tokens) ? $tokens : []);

            $event->addTokens(
                $event->filterTokens(
                    [
                        static::langBarRegex      => $this->translator->trans('mautic.page.token.lang'),
                        static::shareButtonsRegex => $this->translator->trans('mautic.page.token.share'),
                        static::titleRegex        => $this->translator->trans('mautic.core.title'),
                        static::descriptionRegex  => $this->translator->trans('mautic.page.form.metadescription'),
                        static::segmentListRegex  => $this->translator->trans('mautic.page.form.segmentlist'),
                        static::categoryListRegex => $this->translator->trans('mautic.page.form.categorylist'),
                        static::preferredchannel  => $this->translator->trans('mautic.page.form.preferredchannel'),
                        static::channelfrequency  => $this->translator->trans('mautic.page.form.channelfrequency'),
                        static::saveprefsRegex    => $this->translator->trans('mautic.page.form.saveprefs'),
                        static::successmessage    => $this->translator->trans('mautic.page.form.successmessage'),
                        static::identifierToken   => $this->translator->trans('mautic.page.form.leadidentifier'),
                    ]
                )
            );
        }

        if ($event->slotTypesRequested()) {
            $event->addSlotType(
                'text',
                $this->translator->trans('mautic.core.slot.label.text'),
                'font',
                '@MauticCore/Slots/text.html.twig',
                SlotTextType::class,
                1000
            );
            $event->addSlotType(
                'image',
                $this->translator->trans('mautic.core.slot.label.image'),
                'image',
                '@MauticCore/Slots/image.html.twig',
                SlotImageType::class,
                900
            );
            $event->addSlotType(
                'imagecard',
                $this->translator->trans('mautic.core.slot.label.imagecard'),
                'id-card-o',
                '@MauticCore/Slots/imagecard.html.twig',
                SlotImageCardType::class,
                870
            );
            $event->addSlotType(
                'imagecaption',
                $this->translator->trans('mautic.core.slot.label.imagecaption'),
                'image',
                '@MauticCore/Slots/imagecaption.html.twig',
                SlotImageCaptionType::class,
                850
            );
            $event->addSlotType(
                'button',
                $this->translator->trans('mautic.core.slot.label.button'),
                'external-link',
                '@MauticCore/Slots/button.html.twig',
                SlotButtonType::class,
                800
            );
            $event->addSlotType(
                'socialshare',
                $this->translator->trans('mautic.core.slot.label.socialshare'),
                'share-alt',
                '@MauticCore/Slots/socialshare.html.twig',
                SlotSocialShareType::class,
                700
            );
            $event->addSlotType(
                'socialfollow',
                $this->translator->trans('mautic.core.slot.label.socialfollow'),
                'twitter',
                '@MauticCore/Slots/socialfollow.html.twig',
                SlotSocialFollowType::class,
                600
            );
            if ($this->security->isGranted(['page:preference_center:editown', 'page:preference_center:editother'], 'MATCH_ONE')) {
                $event->addSlotType(
                    'segmentlist',
                    $this->translator->trans('mautic.core.slot.label.segmentlist'),
                    'list-alt',
                    '@MauticCore/Slots/segmentlist.html.twig',
                    SlotSegmentListType::class,
                    590
                );
                $event->addSlotType(
                    'categorylist',
                    $this->translator->trans('mautic.core.slot.label.categorylist'),
                    'bookmark-o',
                    '@MauticCore/Slots/categorylist.html.twig',
                    SlotCategoryListType::class,
                    580
                );
                $event->addSlotType(
                    'preferredchannel',
                    $this->translator->trans('mautic.core.slot.label.preferredchannel'),
                    'envelope-o',
                    '@MauticCore/Slots/preferredchannel.html.twig',
                    SlotPreferredChannelType::class,
                    570
                );
                $event->addSlotType(
                    'channelfrequency',
                    $this->translator->trans('mautic.core.slot.label.channelfrequency'),
                    'calendar',
                    '@MauticCore/Slots/channelfrequency.html.twig',
                    SlotChannelFrequencyType::class,
                    560
                );
                $event->addSlotType(
                    'saveprefsbutton',
                    $this->translator->trans('mautic.core.slot.label.saveprefsbutton'),
                    'floppy-o',
                    '@MauticCore/Slots/saveprefsbutton.html.twig',
                    SlotSavePrefsButtonType::class,
                    540
                );

                $event->addSlotType(
                    'successmessage',
                    $this->translator->trans('mautic.core.slot.label.successmessage'),
                    'check',
                    '@MauticCore/Slots/successmessage.html.twig',
                    SlotSuccessMessageType::class,
                    540
                );
            }
            $event->addSlotType(
                'codemode',
                $this->translator->trans('mautic.core.slot.label.codemode'),
                'code',
                '@MauticCore/Slots/codemode.html.twig',
                SlotCodeModeType::class,
                500
            );
            $event->addSlotType(
                'separator',
                $this->translator->trans('mautic.core.slot.label.separator'),
                'minus',
                '@MauticCore/Slots/separator.html.twig',
                SlotSeparatorType::class,
                400
            );
            $event->addSlotType(
                'gatedvideo',
                $this->translator->trans('mautic.core.slot.label.gatedvideo'),
                'video-camera',
                '@MauticCore/Slots/gatedvideo.html.twig',
                GatedVideoType::class,
                300
            );
            $event->addSlotType(
                'dwc',
                $this->translator->trans('mautic.core.slot.label.dynamiccontent'),
                'sticky-note-o',
                '@MauticCore/Slots/dwc.html.twig',
                SlotDwcType::class,
                200
            );
        }

        if ($event->sectionsRequested()) {
            $event->addSection(
                'one-column',
                $this->translator->trans('mautic.core.slot.label.onecolumn'),
                'file-text-o',
                '@MauticCore/Sections/one-column.html.twig',
                null,
                1000
            );
            $event->addSection(
                'two-column',
                $this->translator->trans('mautic.core.slot.label.twocolumns'),
                'columns',
                '@MauticCore/Sections/two-column.html.twig',
                null,
                900
            );
            $event->addSection(
                'three-column',
                $this->translator->trans('mautic.core.slot.label.threecolumns'),
                'th',
                '@MauticCore/Sections/three-column.html.twig',
                null,
                800
            );
        }
    }

    public function onPageDisplay(Events\PageDisplayEvent $event): void
    {
        if (empty($content = $event->getContent())) {
            return;
        }

        $page    = $event->getPage();
        $params  = $event->getParams();
        $content = $this->replaceCommonTokens($content, $page);

        if ($page->getIsPreferenceCenter()) {
            $content = $this->handlePreferenceCenterReplacements($content, $params);
        }

        if ($tokens = $this->tokenHelper->findPageTokens($content, ['source' => ['page', $page->getId()]])) {
            $content = str_ireplace(array_keys($tokens), $tokens, $content);
        }

        $headCloseScripts = $page->getHeadScript();
        if ($headCloseScripts) {
            $content = str_ireplace('</head>', $headCloseScripts."\n</head>", $content);
        }

        $bodyCloseScripts = $page->getFooterScript();
        if ($bodyCloseScripts) {
            $content = str_ireplace('</body>', $bodyCloseScripts."\n</body>", $content);
        }

        $event->setContent($content);
    }

    private function replaceCommonTokens(string $content, Page $page): string
    {
        return str_ireplace([
            static::langBarRegex,
            static::shareButtonsRegex,
            static::titleRegex,
            static::descriptionRegex,
            static::successmessage,
        ], [
            str_contains($content, static::langBarRegex) ? $this->renderLanguageBar($page) : '',
            str_contains($content, static::shareButtonsRegex) ? $this->renderSocialShareButtons() : '',
            str_contains($content, static::titleRegex) ? $page->getTitle() : '',
            str_contains($content, static::descriptionRegex) ? $page->getMetaDescription() : '',
            str_contains($content, static::successmessage) ? $this->renderSuccessMessage() : '',
        ], $content);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function handlePreferenceCenterReplacements(string $content, array $params): string
    {
        $xpath = $this->createDOMXPathForContent($content);

        if ($saveButton = $xpath->query('//*[@data-slot="saveprefsbutton"]//a')->item(0)) {
            $params['saveprefsbutton'] = [
                'style'      => $saveButton->getAttribute('style'),
                'background' => $saveButton->getAttribute('background'),
            ];
        }

        $slotNamesAndConfig = [
            'segmentlist'      => [static::segmentListRegex, (bool) ($params['showContactSegments'] ?? false)],
            'categorylist'     => [static::categoryListRegex, (bool) ($params['showContactCategories'] ?? false)],
            'preferredchannel' => [static::preferredchannel, (bool) ($params['showContactPreferredChannels'] ?? false)],
            'channelfrequency' => [static::channelfrequency, (bool) (($params['showContactFrequency'] ?? false) || ($params['showContactPauseDates'] ?? false))],
            'saveprefsbutton'  => [static::saveprefsRegex, true],
        ];

        foreach ($slotNamesAndConfig as $slotName => list($tokenValue, $shouldShow)) {
            $this->setSlotContentToTokenForReplacement($xpath, $slotName, $tokenValue, $shouldShow);
        }

        $content = $this->replacePreferenceCenterTokens($xpath->document->saveHTML(), $params);

        return $this->wrapPreferenceCenterInFormTag($content, $params);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function replacePreferenceCenterTokens(string $content, array $params): string
    {
        return str_ireplace([
            static::segmentListRegex,
            static::categoryListRegex,
            static::preferredchannel,
            static::channelfrequency,
            static::saveprefsRegex,
        ], [
            str_contains($content, static::segmentListRegex) ? $this->renderSegmentList($params) : '',
            str_contains($content, static::categoryListRegex) ? $this->renderCategoryList($params) : '',
            str_contains($content, static::preferredchannel) ? $this->renderPreferredChannel($params) : '',
            str_contains($content, static::channelfrequency) ? $this->renderChannelFrequency($params) : '',
            str_contains($content, static::saveprefsRegex) ? $this->renderSavePrefs($params) : '',
        ], $content);
    }

    /**
     * @param mixed[] $templateParams
     */
    private function renderTemplate(string $templateName, array $templateParams, string $wrapperTemplate = '', string ...$wrapperTemplateValues): string
    {
        if (!empty($this->renderedContentCache[$templateName])) {
            return $this->renderedContentCache[$templateName];
        }

        $content = trim($this->twig->render($templateName, $templateParams));

        if ($wrapperTemplate) {
            // If the content is not empty, ensure that the $wrapperTemplate contains a place to put it.
            if (!empty($content) && !str_contains($wrapperTemplate, '{templateContent}')) {
                throw new \InvalidArgumentException('Your $wrapperTemplate must contain the string {templateContent} where you want to insert the rendered template content.');
            }

            $content = str_replace('{templateContent}', $content, sprintf($wrapperTemplate, ...$wrapperTemplateValues));
        }

        return $this->renderedContentCache[$templateName] = $content;
    }

    private function renderSocialShareButtons(): string
    {
        return $this->renderTemplate(
            '@MauticPage/SubscribedEvents/PageToken/sharebtn_css.html.twig',
            [],
            '<div class="share-buttons">%s</div>',
            implode('', $this->integrationHelper->getShareButtons())
        );
    }

    private function renderSegmentList(array $params): string
    {
        return $this->renderTemplate(
            '@MauticCore/Slots/segmentlist.html.twig',
            $params,
            '<div class="pref-segmentlist"%s>{templateContent}</div>',
            static::firstSlotAttribute
        );
    }

    private function renderCategoryList(array $params): string
    {
        return $this->renderTemplate(
            '@MauticCore/Slots/categorylist.html.twig',
            $params,
            '<div class="pref-categorylist"%s>{templateContent}</div>',
            static::firstSlotAttribute
        );
    }

    private function renderPreferredChannel(array $params): string
    {
        return $this->renderTemplate(
            '@MauticCore/Slots/preferredchannel.html.twig',
            $params,
            '<div class="pref-preferredchannel">{templateContent}</div>'
        );
    }

    private function renderChannelFrequency(array $params): string
    {
        return $this->renderTemplate(
            '@MauticCore/Slots/channelfrequency.html.twig',
            $params,
            '<div class="pref-channelfrequency">{templateContent}</div>'
        );
    }

    private function renderSavePrefs(array $params): string
    {
        return $this->renderTemplate(
            '@MauticCore/Slots/saveprefsbutton.html.twig',
            $params,
            '<div class="%s"%s>{templateContent}</div>',
            static::saveButtonContainerClass,
            static::firstSlotAttribute
        );
    }

    private function renderSuccessMessage(): string
    {
        return $this->renderTemplate(
            '@MauticCore/Slots/successmessage.html.twig',
            [],
            '<div class="pref-successmessage">{templateContent}</div>'
        );
    }

    private function renderLanguageBar(Page $page): string
    {
        return $this->renderTemplate(
            '@MauticPage/SubscribedEvents/PageToken/langbar.html.twig',
            ['pages' => $this->getRelatedPagesForLanguageBar($page)]
        );
    }

    /**
     * @return array<int,mixed[]>
     */
    private function getRelatedPagesForLanguageBar(Page $page): array
    {
        $related  = [];
        $parent   = $page->getTranslationParent();
        $children = $page->getTranslationChildren();

        if (empty($parent) && empty($children)) {
            return $related;
        }

        // If this page has a parent, then fetch the children from the parent
        if (!empty($parent)) {
            $children = $parent->getTranslationChildren();
        } else {
            // Otherwise this is the parent page.
            $parent = $page;
        }

        if (empty($children)) {
            return $related;
        }

        if ($parent instanceof Page) {
            $related[$parent->getId()] = $this->buildRelatedArrayForPage($parent);
        }

        foreach ($children as $child) {
            $related[$child->getId()] = $this->buildRelatedArrayForPage($child);
        }

        uasort($related, fn ($a, $b): int => strnatcasecmp($a['lang'], $b['lang']));

        return $related;
    }

    /**
     * @return array<string,string>
     */
    private function buildRelatedArrayForPage(Page $page): array
    {
        $language   = $page->getLanguage();
        $translated = $this->translator->trans('mautic.page.lang.'.$language);

        if ($translated == 'mautic.page.lang.'.$language) {
            $translated = $language;
        }

        return [
            'lang' => $translated,
            // Add ntrd to not auto redirect to another language
            'url'  => $this->pageModel->generateUrl($page, false).'?ntrd=1',
        ];
    }

    private function setSlotContentToTokenForReplacement(\DOMXPath $xpath, string $slotName, string $tokenValue, bool $shouldShow): void
    {
        $nodeList = $xpath->query(sprintf('//*[@data-slot="%s"]', $slotName));

        /** @var \DOMElement $node */
        foreach ($nodeList as $node) {
            if ($shouldShow) {
                $node->nodeValue = $tokenValue;
                $node->setAttribute('data-prefs-center', '1');
            } else {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private function createDOMXPathForContent(string $content): \DOMXPath
    {
        $domDocument = new \DOMDocument('1.0', 'utf-8');
        $domDocument->loadHTML(mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, 0xFFFFF], 'UTF-8'), LIBXML_NOERROR);

        return new \DOMXPath($domDocument);
    }

    /**
     * @param mixed[] $params
     */
    private function wrapPreferenceCenterInFormTag(string $content, array $params): string
    {
        if (!isset($params['startform']) || !str_contains($content, 'data-prefs-center')) {
            return $content;
        }

        $xpath = $this->createDOMXPathForContent($content);
        $node  = $this->getFirstNodeThatContainsAPreferenceCenterSlot($xpath);

        if (null === $node) {
            return $content;
        }

        $parentNode = $this->getFirstParentNodeThatContainsAllFormInputs($node);

        $parentNode->insertBefore(new \DOMElement('startform'), $parentNode->firstChild);
        $parentNode->appendChild(new \DOMElement('endform'));

        return str_replace(['<startform></startform>', '<endform></endform>'], [$params['startform'], '</form>'], $xpath->document->saveHTML());
    }

    private function getFirstNodeThatContainsAPreferenceCenterSlot(\DOMXPath $xpath): ?\DOMNode
    {
        // Query if we're using slots.
        $nodeList = $xpath->query('//*[@data-prefs-center="1"]');

        if (!$nodeList->length) {
            // If slots aren't found, query for tokens
            $nodeList = $xpath->query('//*[@data-prefs-center-first="1"]');
        }

        if (false !== $nodeList) {
            return $nodeList->item(0);
        }

        return null;
    }

    private function getFirstParentNodeThatContainsAllFormInputs(\DOMNode $node): \DOMNode
    {
        $content = implode('', array_map([$node->ownerDocument, 'saveHTML'], iterator_to_array($node->childNodes)));

        // Check if the save button exists in the content. If not, try again with the parentNode.
        if (!str_contains($content, static::saveButtonContainerClass)) {
            if (null === $node->parentNode) {
                throw new \RuntimeException("Can't get parent node of #document. Did you forget to insert a save button in your preference center form?");
            }

            return $this->getFirstParentNodeThatContainsAllFormInputs($node->parentNode);
        }

        return $node;
    }
}
