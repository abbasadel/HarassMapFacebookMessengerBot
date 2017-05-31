<?php
namespace HarassMapFbMessengerBot\Handlers;

use Tgallice\FBMessenger\Messenger;
use Tgallice\FBMessenger\Callback\CallbackEvent;
use Tgallice\FBMessenger\Model\Message;
use Tgallice\FBMessenger\Model\QuickReply\Text;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use DateTime;

class GetIncidentsHandler implements Handler
{
    private $messenger;

    private $event;

    private $dbConnection;

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
        if (0 === mb_strpos($this->event->getQuickReplyPayload(), 'GET_INCIDENTS')) {
            $this->getOneReportByOffset();
        }
    }

    private function getOneReportByOffset()
    {
        $offset = 0;
        if (mb_strlen($this->event->getQuickReplyPayload()) > mb_strlen('GET_INCIDENTS_OFFSET_')) {
            $offset = (int) mb_substr($this->event->getQuickReplyPayload(), mb_strlen('GET_INCIDENTS_OFFSET_'));
        }

        $report = $this->getReportByOffset($offset);

        if (! empty($report)) {
            $report = $this->prepareReport($report);
        } else {
            $message = new Message('lang.incidents.no_more');
            $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);
            return;
        }

        $message = new Message($report);
        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);

        $message = new Message('lang.incidents.more');
        $message->setQuickReplies([
            new Text('lang.incidents.next', 'GET_INCIDENTS_OFFSET_' . ($offset + 1)),
        ]);

        $response = $this->messenger->sendMessage($this->event->getSenderId(), $message);
    }

    private function prepareReport(array $report): string
    {
        $preparedReport = '';
        foreach ($report as $key => $value) {
            switch ($key) {
                case 'created_at':
                    $preparedReport .= PHP_EOL . 'lang.incidents.report_date' . $value;
                    break;

                case 'relation':
                    switch ($value) {
                        case 'PERSONAL':
                            $relation = 'lang.incidents.self';
                            break;

                        case 'WITNESS':
                            $relation = 'lang.incidents.witness';
                            break;
                        
                        default:
                            $relation = '';
                            break;
                    }
                    $preparedReport .= PHP_EOL . 'lang.incidents.reporter_relation' . $relation;
                    break;

                case 'details':
                    $preparedReport .= PHP_EOL . 'lang.incidents.details' . $value;
                    break;

                case 'date':
                    $preparedReport .= PHP_EOL . 'lang.incidents.date' . $value;
                    break;

                case 'time':
                    $preparedReport .= PHP_EOL . 'lang.incidents.time' . $value;
                    break;

                case 'harassment_type':
                    switch ($value) {
                        case 'VERBAL':
                            $harassmentType = 'lang.incidents.types.verbal';
                            break;

                        case 'PHYSICAL':
                            $harassmentType = 'lang.incidents.types.physical';
                            break;
                        
                        default:
                            $harassmentType = '';
                            break;
                    }
                    $preparedReport .= PHP_EOL . 'lang.incidents.type' . $harassmentType;
                    break;

                case 'harassment_type_details':
                    switch ($value) {
                        case 'VERBAL1':
                            $harassmentTypeDetails = 'lang.incidents.types.VERBAL1';
                            break;

                        case 'VERBAL2':
                            $harassmentTypeDetails = 'lang.incidents.types.VERBAL2';
                            break;

                        case 'VERBAL3':
                            $harassmentTypeDetails = 'lang.incidents.types.VERBAL3';
                            break;

                        case 'VERBAL4':
                            $harassmentTypeDetails = 'lang.incidents.types.VERBAL4';
                            break;

                        case 'VERBAL5':
                            $harassmentTypeDetails = 'lang.incidents.types.VERBAL5';
                            break;

                        case 'VERBAL6':
                            $harassmentTypeDetails = 'lang.incidents.types.VERBAL6';
                            break;

                        case 'PHYSICAL1':
                            $harassmentTypeDetails = 'lang.incidents.types.PHYSICAL1';
                            break;

                        case 'PHYSICAL2':
                            $harassmentTypeDetails = 'lang.incidents.types.PHYSICAL2';
                            break;

                        case 'PHYSICAL3':
                            $harassmentTypeDetails = 'lang.incidents.types.PHYSICAL3';
                            break;

                        case 'PHYSICAL4':
                            $harassmentTypeDetails = 'lang.incidents.types.PHYSICAL4';
                            break;

                        case 'PHYSICAL5':
                            $harassmentTypeDetails = 'lang.incidents.types.PHYSICAL5';
                            break;

                        case 'PHYSICAL6':
                            $harassmentTypeDetails = 'lang.incidents.types.PHYSICAL6';
                            break;
                        
                        default:
                            $harassmentTypeDetails = '';
                            break;
                    }
                    $preparedReport .= PHP_EOL . 'lang.incidents.more_details' . $harassmentTypeDetails;
                    break;

                case 'assistence_offered':
                    switch ($value) {
                        case '1':
                            $assistenceOffered = 'lang.incidents.yes';
                            break;

                        case '0':
                            $assistenceOffered = 'lang.incidents.no';
                            break;
                        
                        default:
                            $assistenceOffered = '';
                            break;
                    }
                    $preparedReport .= PHP_EOL . 'lang.incidents.assistance' . $assistenceOffered;
                    break;
                
                default:
                    break;
            }
        }

        return $preparedReport;
    }

    private function getReportByOffset(int $offset = 0): array
    {
        $report = $this->dbConnection->fetchAssoc(
            'SELECT * FROM `reports` WHERE `step` = "done" order by updated_at ASC limit 1 offset ?',
            [$offset],
            ['integer']
        );

        return is_array($report) ? $report : [];
    }
}
