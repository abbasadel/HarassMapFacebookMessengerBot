<?php
namespace HarassMapFbMessengerBot\Handlers;

use Tgallice\FBMessenger\Messenger;
use Tgallice\FBMessenger\Callback\CallbackEvent;
use Tgallice\FBMessenger\Callback\MessageEvent;
use Tgallice\FBMessenger\Model\Message;
use Tgallice\FBMessenger\Model\QuickReply\Text;
use Tgallice\FBMessenger\Model\QuickReply\Location;
use Tgallice\FBMessenger\Model\Button\WebUrl;
use Tgallice\FBMessenger\Model\Attachment\Template\Button;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use DateTime;
use Exception;

class ReportIncidentHandler implements Handler
{
    private $messenger;

    private $event;

    private $dbConnection;

    private $steps = [
        'init',
        'relation',
        'details',
        'date',
        'time',
        'harassment_type',
        'harassment_type_details',
        'assistance_offered',
        'location',
        'done'
    ];

    public function __construct(
        Messenger $messenger,
        CallbackEvent $event,
        Connection $dbConnection
    ) {
        $this->messenger = $messenger;
        $this->event = $event;
        $this->dbConnection = $dbConnection;
    }

    public function handle()
    {
        if ($this->event->getQuickReplyPayload() === 'REPORT_INCIDENT') {
            $this->startReport();
        } elseif (0 === mb_strpos($this->event->getQuickReplyPayload(), 'REPORT_INCIDENT_RELATIONSHIP')) {
            $this->saveRelationship();
        } elseif (! $this->event->isQuickReply() && $this->event->getMessage()->hasText()) {
            $this->saveDetails();
        } elseif (0 === mb_strpos($this->event->getQuickReplyPayload(), 'REPORT_INCIDENT_DATE')) {
            $this->saveDate();
        } elseif (0 === mb_strpos($this->event->getQuickReplyPayload(), 'REPORT_INCIDENT_TIME')) {
            $this->saveTime();
        } elseif (0 === mb_strpos($this->event->getQuickReplyPayload(), 'REPORT_INCIDENT_HARASSMENT_TYPE')) {
            $this->saveHarassmentType();
        } elseif (0 === mb_strpos($this->event->getQuickReplyPayload(), 'REPORT_INCIDENT_HARASSMENT_DETAILS')) {
            $this->saveHarassmentDetails();
        } elseif (0 === mb_strpos($this->event->getQuickReplyPayload(), 'REPORT_INCIDENT_ASSISTANCE_OFFERED')) {
            $this->saveAssistanceOffered();
        } elseif (! $this->event->isQuickReply() && $this->event->getMessage()->hasLocation()) {
            $this->saveLocation();
        }
    }

    private function saveLocation()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());
        $report = $this->getInProgressReportByUser($user['id']);

        $this->saveAnswerToReport(
            'latitude',
            $this->event->getMessage()->getLatitude(),
            $report
        );
        $this->saveAnswerToReport(
            'longitude',
            $this->event->getMessage()->getLongitude(),
            $report
        );

        $message = new Message('lang.report.confirm1');
        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $message = new Message('lang.report.confirm2');
        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $message = new Message('lang.report.confirm3');
        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $elements = [
            new WebUrl('lang.links.nazra.title', 'lang.links.nazra.url'),
            new WebUrl('lang.links.hm.title', 'lang.links.hm.url'),
        ];

        $message = new Button('lang.report.contact_us', $elements);
        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($report);
    }

    private function saveAssistanceOffered()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());
        $report = $this->getInProgressReportByUser($user['id']);

        $assistenceOffered = mb_substr(
            $this->event->getQuickReplyPayload(),
            mb_strlen('REPORT_INCIDENT_ASSISTANCE_OFFERED_')
        );

        $this->saveAnswerToReport(
            'assistence_offered',
            $assistenceOffered === 'YES' ? 1 : 0,
            $report
        );

        $message = new Message('lang.report.share_location');
        $message->setQuickReplies([
            new Location(),
        ]);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($report);
    }

    private function saveHarassmentDetails()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());
        $report = $this->getInProgressReportByUser($user['id']);

        $harassmentDetails = mb_substr(
            $this->event->getQuickReplyPayload(),
            mb_strlen('REPORT_INCIDENT_HARASSMENT_DETAILS_')
        );

        $this->saveAnswerToReport(
            'harassment_type_details',
            $harassmentDetails,
            $report
        );

        $message = new Message('lang.report.assistance');
        $message->setQuickReplies([
            new Text('نعم', 'REPORT_INCIDENT_ASSISTANCE_OFFERED_YES'),
            new Text('لا', 'REPORT_INCIDENT_ASSISTANCE_OFFERED_NO'),
        ]);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($report);
    }

    private function saveHarassmentType()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());
        $report = $this->getInProgressReportByUser($user['id']);

        $harassmentType = mb_substr(
            $this->event->getQuickReplyPayload(),
            mb_strlen('REPORT_INCIDENT_HARASSMENT_TYPE_')
        );

        $this->saveAnswerToReport(
            'harassment_type',
            $harassmentType,
            $report
        );

        $message = new Message('lang.report.type');
        switch ($harassmentType) {
            case 'VERBAL':
                $harassmentTypeDetails = [
                    new Text('lang.incident.types.VERBAL1', 'REPORT_INCIDENT_HARASSMENT_DETAILS_VERBAL1'),
                    new Text('lang.incident.types.VERBAL2', 'REPORT_INCIDENT_HARASSMENT_DETAILS_VERBAL2'),
                    new Text('lang.incident.types.VERBAL3', 'REPORT_INCIDENT_HARASSMENT_DETAILS_VERBAL3'),
                    new Text('lang.incident.types.VERBAL4', 'REPORT_INCIDENT_HARASSMENT_DETAILS_VERBAL4'),
                    new Text('lang.incident.types.VERBAL5', 'REPORT_INCIDENT_HARASSMENT_DETAILS_VERBAL5'),
                    new Text('lang.incident.types.VERBAL6', 'REPORT_INCIDENT_HARASSMENT_DETAILS_VERBAL6'),
                ];
                break;

            case 'PHYSICAL':
                $harassmentTypeDetails = [
                    new Text('lang.incident.types.PHYSICAL1', 'REPORT_INCIDENT_HARASSMENT_DETAILS_PHYSICAL1'),
                    new Text('lang.incident.types.PHYSICAL2', 'REPORT_INCIDENT_HARASSMENT_DETAILS_PHYSICAL2'),
                    new Text('lang.incident.types.PHYSICAL3', 'REPORT_INCIDENT_HARASSMENT_DETAILS_PHYSICAL3'),
                    new Text('lang.incident.types.PHYSICAL4', 'REPORT_INCIDENT_HARASSMENT_DETAILS_PHYSICAL4'),
                    new Text('lang.incident.types.PHYSICAL5', 'REPORT_INCIDENT_HARASSMENT_DETAILS_PHYSICAL5'),
                    new Text('lang.incident.types.PHYSICAL6', 'REPORT_INCIDENT_HARASSMENT_DETAILS_PHYSICAL6'),
                ];
                break;
        }
        $message->setQuickReplies($harassmentTypeDetails);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($report);
    }

    private function saveTime()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());
        $report = $this->getInProgressReportByUser($user['id']);

        $timeHour = mb_substr($this->event->getQuickReplyPayload(), mb_strlen('REPORT_INCIDENT_TIME_'));
        $time = new DateTime($timeHour . ':00');

        $this->saveAnswerToReport(
            'time',
            $time->format('H:i:s'),
            $report
        );

        $message = new Message('lang.incident.type');
        $message->setQuickReplies([
            new Text('lang.incident.types.verbal', 'REPORT_INCIDENT_HARASSMENT_TYPE_VERBAL'),
            new Text('lang.incident.types.physical', 'REPORT_INCIDENT_HARASSMENT_TYPE_PHYSICAL'),
        ]);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($report);
    }

    private function saveDate()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());
        $report = $this->getInProgressReportByUser($user['id']);

        $dateMessage = mb_substr($this->event->getQuickReplyPayload(), mb_strlen('REPORT_INCIDENT_DATE_'));
        switch ($dateMessage) {
            case 'TODAY':
                $date = new DateTime();
                $date = $date->format('Y-m-d');
                break;

            case 'YESTERDAY':
                $date = new DateTime();
                $date->setTimestamp(strtotime('yesterday'));
                $date = $date->format('Y-m-d');
                break;

            case '2_DAYS_AGO':
                $date = new DateTime();
                $date->setTimestamp(strtotime('2 days ago'));
                $date = $date->format('Y-m-d');
                break;

            case 'DATE_EARLIER':
            default:
                $date = new DateTime();
                $date->setTimestamp(strtotime('1 week ago'));
                $date = $date->format('Y-m-d');
                break;
        }

        $this->saveAnswerToReport(
            'date',
            $date,
            $report
        );

        $message = new Message('lang.report.time');
        $message->setQuickReplies([
            new Text('lang.report.times.TIME_12', 'REPORT_INCIDENT_TIME_12'),
            new Text('lang.report.times.TIME_15', 'REPORT_INCIDENT_TIME_15'),
            new Text('lang.report.times.TIME_18', 'REPORT_INCIDENT_TIME_18'),
            new Text('lang.report.times.TIME_00', 'REPORT_INCIDENT_TIME_00'),
            new Text('lang.report.times.TIME_6', 'REPORT_INCIDENT_TIME_6'),
        ]);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($report);
    }

    private function saveDetails()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());
        $report = $this->getInProgressReportByUser($user['id']);

        $this->saveAnswerToReport(
            'details',
            $this->event->getMessageText(),
            $report
        );

        $message = new Message('امتى حصل التحرش؟');
        $message->setQuickReplies([
            new Text('lang.report.dates.TODAY', 'REPORT_INCIDENT_DATE_TODAY'),
            new Text('lang.report.dates.YESTERDAY', 'REPORT_INCIDENT_DATE_YESTERDAY'),
            new Text('lang.report.dates.DAY_BEFORE_YESTERDAY', 'REPORT_INCIDENT_DATE_DAY_BEFORE_YESTERDAY'),
            new Text('lang.report.dates.EARLIER', 'REPORT_INCIDENT_DATE_EARLIER'),
        ]);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($report);
    }

    private function saveRelationship()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());
        $report = $this->getInProgressReportByUser($user['id']);

        $this->saveAnswerToReport(
            'relation',
            mb_substr($this->event->getQuickReplyPayload(), mb_strlen('REPORT_INCIDENT_RELATIONSHIP_')),
            $report
        );

        $message = new Message('lang.report.detail');

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($report);
    }

    private function startReport()
    {
        $user = $this->getUserByPSID($this->event->getSenderId());

        $this->dbConnection->insert('reports', [
            'user_id' => $user['id'],
            'step' => reset($this->steps),
        ]);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), 'lang.report.privacy');

        $message = new Message('lang.report.relation');
        $message->setQuickReplies([
            new Text('lang.report.self', 'REPORT_INCIDENT_RELATIONSHIP_PERSONAL'),
            new Text('lang.report.witness', 'REPORT_INCIDENT_RELATIONSHIP_WITNESS')
        ]);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $this->advanceReportStep($this->getInProgressReportByUser($user['id']));
    }

    private function getUserByPSID(string $psid): array
    {
        $user = $this->dbConnection->fetchAssoc(
            'SELECT * FROM `users` WHERE `psid` = ?',
            [$psid]
        );

        if (! is_array($user)) {
            $userProfile = $this->messenger->getUserProfile($this->event->getSenderId());

            $this->dbConnection->insert('users', [
                'psid' => $this->event->getSenderId(),
                'first_name' => $userProfile->getFirstName(),
                'last_name' => $userProfile->getLastName(),
                'locale' => $userProfile->getLocale(),
                'timezone' => $userProfile->getTimezone(),
                'gender' => $userProfile->getGender(),
                'preferred_language' => $userProfile->getLocale() ?? self::LOCALE_DEFAULT,
            ]);

            $user = $this->dbConnection->fetchAssoc(
                'SELECT * FROM `users` WHERE `psid` = ?',
                [$psid]
            );
        }

        return $user;
    }

    private function getInProgressReportByUser(int $id): array
    {
        $doneStatus = $this->steps[count($this->steps) - 1];
        $report = $this->dbConnection->fetchAssoc(
            'SELECT * FROM `reports` WHERE `user_id` = ? AND `step` != "' . $doneStatus . '" order by updated_at DESC limit 1',
            [$id]
        );

        if (!is_array($report)) {
            throw new Exception('Can not find report for given user!');
        }

        return $report;
    }

    private function advanceReportStep(array $report)
    {
        $this->dbConnection->update(
            'reports',
            [
                'step' => $this->steps[array_search($report['step'], $this->steps) + 1]
            ],
            [
                'id' => $report['id']
            ]
        );
    }

    private function saveAnswerToReport(string $field, string $answer, array $report)
    {
        $this->dbConnection->update(
            'reports',
            [
                $field => $answer
            ],
            [
                'id' => $report['id']
            ]
        );
    }
}
