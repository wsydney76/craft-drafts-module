<?php

namespace project\modules\drafts;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\RegisterElementSourcesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\i18n\PhpMessageSource;
use craft\web\View;
use yii\base\Event;
use yii\base\Module;

class DraftsModule extends Module
{
    public function init()
    {

        if (!Craft::$app->request->isCpRequest) {
            return;
        }

        // Set template root for cp requests
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $event) {
            $event->roots['drafts'] = __DIR__ . DIRECTORY_SEPARATOR . 'templates';
        }
        );

        // Register translation category
        Craft::$app->i18n->translations['drafts'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en',
            'basePath' => __DIR__ . '/messages',
            'allowOverrides' => true,
        ];

        // Inject template into entries edit screen
        $user = Craft::$app->user->identity;
        if ($user && !$user->disableDraftHints) {
            Craft::$app->view->hook('cp.entries.edit.details', function(array $context) {
                if ($context['entry'] === null) {
                    return '';
                }
                return Craft::$app->view->renderTemplate(
                    'drafts/entries_edit_details',
                    ['context' => $context]);
            });
        }

        // Register element index source and table fields for drafts
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_SOURCES, function(RegisterElementSourcesEvent $event) {

            // TODO: No idea whether permissions are handled correctly here
            $event->sources[] = [
                'key' => 'drafts',
                'label' => Craft::t('drafts', 'All drafts'),
                'criteria' => [
                    'drafts' => true,
                    'editable' => true

                ],
                'defaultSort' => [
                    0 => 'dateCreated',
                    1 => 'desc'
                ]
            ];
        }
        );
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['isUnsavedDraft'] = ['label' => Craft::t('drafts', 'Unsaved?')];
            $event->tableAttributes['draftName'] = ['label' => Craft::t('drafts', 'Draft Name')];
            $event->tableAttributes['draftNotes'] = ['label' => Craft::t('drafts', 'Draft Notes')];
            $event->tableAttributes['creatorId'] = ['label' => Craft::t('drafts', 'Draft Creator')];
            $event->tableAttributes['hasDrafts'] = ['label' => Craft::t('drafts', 'Has Drafts')];
        }
        );
        Event::on(
            Entry::class,
            Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, function(SetElementTableAttributeHtmlEvent $event) {
            // TODO: avoid error if draft fields are added to a non draft source

            if ($event->attribute == 'isUnsavedDraft') {
                $event->handled = true;
                /** @var Entry $entry */
                $entry = $event->sender;
                $event->html = $entry->isUnsavedDraft ? '<span class="status active"></span>' : '';
            }
            if ($event->attribute == 'creatorId') {
                $event->handled = true;
                /** @var Entry $entry */
                $entry = $event->sender;
                /** @var User $user */
                $user = User::find()->id($entry->creatorId)->one();

                $event->html = $user ? $user->name : '';
            }

            if ($event->attribute == 'hasDrafts') {
                $event->handled = true;
                /** @var Entry $entry */
                $entry = $event->sender;

                $count = Entry::find()->draftOf($entry->getSourceId())->count();
                $event->html = $count ? '<span class="status pending"></span> ' . $count : '';
            }
        }
        );
    }
}
