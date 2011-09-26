<?php
require_once 'php-amqplib/amqp.inc';

class RabbitMq
{
    static public $doPush = FALSE;

    /**
     * Sets a flag to push the schedule at the end of the request.
     */
    public static function PushSchedule() {
        Application_Model_RabbitMq::$doPush = TRUE;
    }

    public static function SendMessageToPypo($event_type, $md)
    {
        global $CC_CONFIG;

        $md["event_type"] = $event_type;

        $conn = new AMQPConnection($CC_CONFIG["rabbitmq"]["host"],
                                         $CC_CONFIG["rabbitmq"]["port"],
                                         $CC_CONFIG["rabbitmq"]["user"],
                                         $CC_CONFIG["rabbitmq"]["password"]);
        $channel = $conn->channel();
        $channel->access_request($CC_CONFIG["rabbitmq"]["vhost"], false, false, true, true);

        $EXCHANGE = 'airtime-pypo';
        $channel->exchange_declare($EXCHANGE, 'direct', false, true);

        $data = json_encode($md);
        $msg = new AMQPMessage($data, array('content_type' => 'text/plain'));

        $channel->basic_publish($msg, $EXCHANGE);
        $channel->close();
        $conn->close();
    }

    public static function SendMessageToMediaMonitor($event_type, $md)
    {
        global $CC_CONFIG;

        $md["event_type"] = $event_type;

        $conn = new AMQPConnection($CC_CONFIG["rabbitmq"]["host"],
                                         $CC_CONFIG["rabbitmq"]["port"],
                                         $CC_CONFIG["rabbitmq"]["user"],
                                         $CC_CONFIG["rabbitmq"]["password"]);
        $channel = $conn->channel();
        $channel->access_request($CC_CONFIG["rabbitmq"]["vhost"], false, false, true, true);

        $EXCHANGE = 'airtime-media-monitor';
        $channel->exchange_declare($EXCHANGE, 'direct', false, true);

        $data = json_encode($md);
        $msg = new AMQPMessage($data, array('content_type' => 'text/plain'));

        $channel->basic_publish($msg, $EXCHANGE);
        $channel->close();
        $conn->close();
    }
    
    public static function SendMessageToShowRecorder($event_type)
    {
        global $CC_CONFIG;

        $conn = new AMQPConnection($CC_CONFIG["rabbitmq"]["host"],
                                        $CC_CONFIG["rabbitmq"]["port"],
                                        $CC_CONFIG["rabbitmq"]["user"],
                                        $CC_CONFIG["rabbitmq"]["password"]);
        $channel = $conn->channel();
        $channel->access_request($CC_CONFIG["rabbitmq"]["vhost"], false, false, true, true);
    
        $EXCHANGE = 'airtime-show-recorder';
        $channel->exchange_declare($EXCHANGE, 'direct', false, true);
    
        $now = new DateTime("@".time());
        $end_timestamp = new DateTime("@".time() + 3600*2);

        $temp['event_type'] = $event_type;
        if($event_type = "update_schedule"){
            $temp['shows'] = Application_Model_Show::getShows($now->format("Y-m-d H:i:s"), $end_timestamp->format("Y-m-d H:i:s"), $excludeInstance=NULL, $onlyRecord=TRUE);
        }
        $data = json_encode($temp);
        $msg = new AMQPMessage($data, array('content_type' => 'text/plain'));
    
        $channel->basic_publish($msg, $EXCHANGE);
        $channel->close();
        $conn->close();
    }
}

