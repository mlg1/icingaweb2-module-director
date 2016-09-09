<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_hostgroup';

    public function supportsAssignments()
    {
        return true;
    }
}
