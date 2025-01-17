<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\Format\LocalTimeFormat;
use Icinga\Module\Director\Util;
use ipl\Html\BaseHtmlElement;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class ActivityLogTable extends ZfQueryBasedTable
{
    protected $filters = [];

    protected $lastDeployedId;

    protected $extraParams = [];

    protected $columnCount;

    protected $hasObjectFilter = false;

    /** @var BaseHtmlElement */
    protected $currentHead;

    /** @var BaseHtmlElement */
    protected $currentBody;

    protected $searchColumns = [
        'author',
        'object_name',
        'object_type',
    ];

    /** @var LocalTimeFormat */
    protected $timeFormat;

    public function __construct($db)
    {
        parent::__construct($db);
        $this->timeFormat = new LocalTimeFormat();
    }

    public function assemble()
    {
        $this->getAttributes()->add('class', 'activity-log');
    }

    public function setLastDeployedId($id)
    {
        $this->lastDeployedId = $id;
        return $this;
    }

    public function renderRow($row)
    {
        $this->splitByDay($row->ts_change_time);
        $action = 'action-' . $row->action. ' ';
        if ($row->id > $this->lastDeployedId) {
            $action .= 'undeployed';
        } else {
            $action .= 'deployed';
        }

        return $this::tr([
            $this::td($this->makeLink($row))->setSeparator(' '),
            $this::td($this->timeFormat->getTime($row->ts_change_time))
        ])->addAttributes(['class' => $action]);
    }

    protected function makeLink($row)
    {
        $type = $row->object_type;
        $name = $row->object_name;
        if (substr($type, 0, 7) === 'icinga_') {
            $type = substr($type, 7);
        }

        if (Util::hasPermission('director/showconfig')) {
            // Later on replacing, service_set -> serviceset

            // multi column key :(
            if ($type === 'service' || $this->hasObjectFilter) {
                $object = "\"$name\"";
            } else {
                $object = Link::create(
                    "\"$name\"",
                    'director/' . str_replace('_', '', $type),
                    ['name' => $name],
                    ['title' => $this->translate('Jump to this object')]
                );
            }

            return [
                '[' . $row->author . ']',
                Link::create(
                    $row->action,
                    'director/config/activity',
                    array_merge(['id' => $row->id], $this->extraParams),
                    ['title' => $this->translate('Show details related to this change')]
                ),
                str_replace('_', ' ', $type),
                $object
            ];
        } else {
            return sprintf(
                '[%s] %s %s "%s"',
                $row->author,
                $row->action,
                $type,
                $name
            );
        }
    }

    public function filterObject($type, $name)
    {
        $this->hasObjectFilter = true;
        $this->filters[] = ['l.object_type = ?', $type];
        $this->filters[] = ['l.object_name = ?', $name];

        return $this;
    }

    public function filterHost($name)
    {
        $db = $this->db();
        $filter = '%"host":' . json_encode($name) . '%';
        $this->filters[] = ['('
            . $db->quoteInto('l.old_properties LIKE ?', $filter)
            . ' OR '
            . $db->quoteInto('l.new_properties LIKE ?', $filter)
            . ')', null];

        return $this;
    }

    public function getColumns()
    {
        return [
            'author'          => 'l.author',
            'action'          => 'l.action_name',
            'object_name'     => 'l.object_name',
            'object_type'     => 'l.object_type',
            'id'              => 'l.id',
            'change_time'     => 'l.change_time',
            'ts_change_time'  => 'UNIX_TIMESTAMP(l.change_time)',
        ];
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(
            ['l' => 'director_activity_log'],
            $this->getColumns()
        )->order('change_time DESC')->order('id DESC')->limit(100);

        foreach ($this->filters as $filter) {
            $query->where($filter[0], $filter[1]);
        }

        return $query;
    }
}
