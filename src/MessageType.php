<?php

namespace Klsoft\Yii3Swoole;

enum MessageType: string
{
    case Event = 'Event';
    case Info = 'Info';
    case Error = 'Error';
}
