<?php

require_once('./Services/WebServices/SOAP/classes/class.ilAbstractSoapMethod.php');

/**
 * Class lfGetProgressInfoSoapMethod
 *
 * Returns detailed information about learning Progresses.
 *
 * @author Marvin Barz <barz@leifos.de>
 */
class lfGetExtendedProgressInfoSoapMethod extends ilAbstractSoapMethod {

    protected static $DELETE_PROGRESS_FILTER_TYPES = array('sahs', 'tst');

    private $writer;

    const PROGRESS_FILTER_ALL = 0;
    const PROGRESS_FILTER_IN_PROGRESS = 1;
    const PROGRESS_FILTER_COMPLETED = 2;
    const PROGRESS_FILTER_FAILED = 3;
    const PROGRESS_FILTER_NOT_ATTEMPTED = 4;

    const ASSIGN_FILTER_CURRENT = 0;
    const ASSIGN_FILTER_ASSIGNED = 1;
    const ASSIGN_FILTER_ALL = 2;

    const SOAP_LP_ERROR_AUTHENTICATION = 50;
    const SOAP_LP_ERROR_INVALID_FILTER = 52;
    const SOAP_LP_ERROR_INVALID_REF_ID = 54;
    const SOAP_LP_ERROR_LP_NOT_AVAILABLE = 56;
    const SOAP_LP_ERROR_NO_PERMISSION = 58;
    const SOAP_LP_ERROR_LP_NOT_ENABLED = 60;

    protected static $PROGRESS_INFO_TYPES = array(
        self::PROGRESS_FILTER_ALL,
        self::PROGRESS_FILTER_IN_PROGRESS,
        self::PROGRESS_FILTER_COMPLETED,
        self::PROGRESS_FILTER_FAILED,
        self::PROGRESS_FILTER_NOT_ATTEMPTED
    );

    /**
     * @inheritdoc
     */
    public function getName() {
        return 'getExtendedProgressInfo';
    }

    /**
     * @inheritdoc
     */
    public function getInputParams() {
        return array(
            'sid'             => 'xsd:string',
            'ref_id'          => 'xsd:int',
            'progress_filter' => 'tns:intArray',
            'assigned_filter' => 'xsd:int',
            'object_types'    => 'tns:stringArray'
        );
    }

    /**
     * @inheritdoc
     */
    public function getOutputParams() {
        return array(
            'user_results' => 'xsd:string',
        );
    }

    /**
     * @inheritdoc
     */
    public function getServiceNamespace() {
        return 'urn:ilUserAdministration';
    }

    /**
     * @inheritdoc
     */
    public function getDocumentation() {
        return "Returns detailed learning progress information of an object by the given Ref-ID";
    }

    /**
     * Gets information about obj_id and type of given ref_id
     * @param int $ref_id
     * @return array
     * @throws ilSoapPluginException
     */
    private function getObjectInfo($ref_id) {

        $obj_id = ilObject::_lookupObjId($ref_id);
        if (!$obj_id) {
            throw new ilSoapPluginException("Could not load object");
        }

        $type = ilObject::_lookupType($obj_id);
        if (!$type) {
            throw new ilSoapPluginException("Could not load object");
        }

        return array(
            'ref_id' => $ref_id,
            'obj_id' => $obj_id,
            'type'   => $type
        );
    }

    /**
     * Gets learning progress status of object according to given filter
     * @param int $filter
     * @param int $obj_id
     * @param int $ref_id
     * @return array
     */
    private function getObjectStatusInfo($filter, $obj_id, $ref_id) {

        if($filter === self::PROGRESS_FILTER_COMPLETED) {
            $status = ilLPStatusWrapper::_getCompleted($obj_id);
        } elseif($filter === self::PROGRESS_FILTER_IN_PROGRESS) {
            $status = ilLPStatusWrapper::_getInProgress($obj_id);
        } elseif($filter === self::PROGRESS_FILTER_FAILED) {
            $status = ilLPStatusWrapper::_getFailed($obj_id);
        } elseif($filter === self::PROGRESS_FILTER_NOT_ATTEMPTED) {
            $status = ilLPStatusWrapper::_getNotAttempted($obj_id);
        } else {
            return array();
        }

        $status = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
            'read_learning_progress',
            ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
            $ref_id,
            $status
        );


        return $status;
    }

    //TODO: Move this function to ilLPMarks when implementing into trunk

    /**
     * Gets last time when status of learning progression changed as UTC time-value
     * @param int $a_usr_id
     * @param int $a_obj_id
     * @return array|int|string
     */
    private function getStatusChanged($a_usr_id, $a_obj_id) {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = "SELECT * FROM ut_lp_marks " .
            "WHERE usr_id = " . $ilDB->quote($a_usr_id, 'integer') . " " .
            "AND obj_id = " . $ilDB->quote($a_obj_id, 'integer');

        $res = $ilDB->query($query);
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            try {
                $status_changed = new ilDateTime($row->status_changed, IL_CAL_DATETIME);
                return $status_changed->get(IL_CAL_DATETIME, '', \ilTimeZone::UTC);
            } catch (ilDateTimeException $e) {
                $DIC->logger()->wsrv()->warning($e->getMessage());
                return '';
            }
        }
        return '';
    }

    /**
     * Creates Status XML-Element
     * @param int $filter
     * @param int $obj_id
     * @param int $ref_id
     * @return mixed
     */
    private function createObjectStatusElement($filter, $obj_id, $ref_id) {

        $status = count($this->getObjectStatusInfo($filter, $obj_id, $ref_id));

        return $this->writer->xmlElement(
            'Status',
            array(
                'type'  => $filter,
                'num'	=> (int) $status
            )
        );
    }

    /**
     * Creates User XML-Element
     * @param int $filter
     * @param int $obj_id
     * @param int $ref_id
     */
    private function createUserElement($filter, $obj_id, $ref_id)
    {
        $users = $this->getObjectStatusInfo($filter, $obj_id, $ref_id);

        foreach ($users  as $user_id) {

            $import_id = ilObjUser::_lookupImportId($user_id);

            $status_changed = $this->getStatusChanged($user_id, $obj_id);

            $this->writer->xmlStartTag(
                'User',
                array(
                    'user_id'        => $user_id,
                    'import_id'      => $import_id,
                    'status'         => $filter,
                    'status_changed' => $status_changed
                )
            );

            $info    = ilObjUser::_lookupName($user_id);
            $ext_acc = ilObjUser::_lookupExternalAccount($user_id);
            $auth_mode = ilObjUser::_lookupAuthMode($user_id);

            $this->writer->xmlElement('Login', array(), (string) $info['login']);
            $this->writer->xmlElement('ExternalAccount', array(), (string) $ext_acc);
            $this->writer->xmlElement('AuthMode', array(), (string) $auth_mode);
            $this->writer->xmlElement('Firstname', array(), (string) $info['firstname']);
            $this->writer->xmlElement('Lastname', array(), (string) $info['lastname']);
            $this->writer->xmlEndTag('User');
        }
    }

    /**
     * Creates XML for Sub-Objects
     * @param int $ref_id
     * @param int $progress_filter
     * @param array $object_types
     * @throws ilSoapPluginException
     */
    private function createSubXml($ref_id, $progress_filter,$object_types) {

        $lpi_attr = $this->getObjectInfo($ref_id);

        if(!empty($object_types) && !in_array($lpi_attr['type'], $object_types)) {
            return;
        }

        $this->writer->xmlStartTag(
            'LearningProgressInfo',
            array(
                'ref_id' => $lpi_attr['ref_id'],
                'obj_id' => $lpi_attr['obj_id'],
                'type' => $lpi_attr['type']
            )
        );

        $this->writer->xmlElement(
            'LearningProgressInfoTitle',
            array(),
            ilObject::_lookupTitle($lpi_attr['obj_id'])
        );


        $this->writer->xmlElement(
            'LearningProgressInfoDescription',
            array(),
            ilObject::_lookupDescription($lpi_attr['obj_id'])
        );

        $this->writer->xmlStartTag('LearningProgressSummary');

        if(in_array(self::PROGRESS_FILTER_ALL, $progress_filter)) {
            foreach (self::$PROGRESS_INFO_TYPES as $filter) {
                if($filter === self::PROGRESS_FILTER_ALL) {
                    continue;
                } else {
                    $this->createObjectStatusElement($filter, $lpi_attr['obj_id'], $lpi_attr['ref_id']);
                }
            }
        } else {
            foreach ($progress_filter as $filter) {
                $this->createObjectStatusElement($filter, $lpi_attr['obj_id'], $lpi_attr['ref_id']);
            }
        }

        $this->writer->xmlEndTag('LearningProgressSummary');

        $this->writer->xmlStartTag('UserProgress');

        if(in_array(self::PROGRESS_FILTER_ALL, $progress_filter)) {
            foreach (self::$PROGRESS_INFO_TYPES as $filter) {
                if($filter === self::PROGRESS_FILTER_ALL) {
                    continue;
                } else {
                    $this->createUserElement($filter, $lpi_attr['obj_id'], $lpi_attr['ref_id']);
                }
            }
        } else {
            foreach ($progress_filter as $filter) {
                $this->createUserElement($filter, $lpi_attr['obj_id'], $lpi_attr['ref_id']);
            }
        }

        $this->writer->xmlEndTag('UserProgress');

        $this->writer->xmlEndTag('LearningProgressInfo');
    }

    /**
     * @inheritdoc
     */
    public function execute(array $params) {

        $this->checkParameters($params);
        $session_id = (!empty($params[0])) ? $params[0] : '';
        $ref_id = (!empty($params[1])) ? (int)$params[1] : 0;
        $progress_filter = (!empty($params[2])) ? $params[2] : array(0);
        $assign_filter = (!empty($params[3])) ? (int)$params[3] : 0;
        $object_types = (!empty($params[4])) ? $params[4] : array();
        $this->initIliasAndCheckSession($session_id); // Throws exception if session is not valid
        if (!$ref_id) {
            throw new ilSoapPluginException("No valid Ref-Id provided");
        }

        global $DIC;

        $ilAccess = $DIC->access();

        $DIC->logger()->usr()->dump($object_types);

        // Check session
        if (!$this->__checkSession($session_id)) {
            throw new ilSoapPluginException(
                'Error ' . self::SOAP_LP_ERROR_AUTHENTICATION . ':' . $this->__getMessage(),
                self::SOAP_LP_ERROR_AUTHENTICATION
            );
        }

        // Check filter
        if (array_diff((array) $progress_filter, self::$PROGRESS_INFO_TYPES)) {
            throw new ilSoapPluginException(
                'Error ' . self::SOAP_LP_ERROR_INVALID_FILTER . ': Invalid filter type given',
                self::SOAP_LP_ERROR_INVALID_FILTER
            );
        }

        // Check LP enabled
        if (!ilObjUserTracking::_enabledLearningProgress()) {
            throw new ilSoapPluginException(
                'Error ' . self::SOAP_LP_ERROR_LP_NOT_ENABLED . ': Learning progress not enabled in ILIAS',
                self::SOAP_LP_ERROR_LP_NOT_ENABLED
            );
        }

        $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);
        if (!$obj instanceof ilObject) {
            throw new ilSoapPluginException(
                'Error ' . self::SOAP_LP_ERROR_INVALID_REF_ID . ': Invalid reference id ' . $ref_id . ' given',
                self::SOAP_LP_ERROR_INVALID_REF_ID
            );
        }

        // check lp available
        include_once './Services/Tracking/classes/class.ilLPObjSettings.php';
        $mode = ilLPObjSettings::_lookupDBMode($obj->getId());
        if ($mode == ilLPObjSettings::LP_MODE_UNDEFINED) {
            throw new ilSoapPluginException(
                'Error ' . self::SOAP_LP_ERROR_LP_NOT_AVAILABLE . ': Learning progress not available for objects of type ' .
                $obj->getType(),
                self::SOAP_LP_ERROR_LP_NOT_AVAILABLE
            );
        }

        // check rbac
        /**
         * @var ilAccess
         */
        if (!$ilAccess->checkRbacOrPositionPermissionAccess('read_learning_progress', 'read_learning_progress', $ref_id)) {
            throw new ilSoapPluginException(
                'Error ' . self::SOAP_LP_ERROR_NO_PERMISSION . ': No Permission to access learning progress in this object',
                self::SOAP_LP_ERROR_NO_PERMISSION
            );
        }

        $this->writer = new ilXmlWriter();

        $main_lpi_attr = $this->getObjectInfo($ref_id);

        $this->writer->xmlStartTag(
            'LearningProgressInfo',
            array(
                'ref_id' => $main_lpi_attr['ref_id'],
                'obj_id' => $main_lpi_attr['obj_id'],
                'type' => $main_lpi_attr['type']
            )
        );


        if($assign_filter == self::ASSIGN_FILTER_ASSIGNED) {
            // alle aktivierten und zugeordneten
            $mode = \ilObjectLP::getInstance(\ilObject::_lookupObjId($main_lpi_attr['ref_id']))->getCurrentMode();
            $collection = \ilLPCollection::getInstanceByMode($main_lpi_attr['obj_id'], $mode)->getItems();

            //Momentan werden auch relevante Dateien geliefert, deren LP deaktiviert ist
            foreach ($collection as $ref_id) {
                $this->createSubXml($ref_id,$progress_filter,$object_types);
            }

        } else if($assign_filter == self::ASSIGN_FILTER_ALL) {
            // alle möglichen objekte
            $mode  = \ilLPObjSettings::LP_MODE_COLLECTION;
            $collection = \ilLPCollection::getInstanceByMode(\ilObject::_lookupObjId($main_lpi_attr['ref_id']), $mode);
            $all_possible = $collection->getPossibleItems($main_lpi_attr['ref_id']);

             foreach($all_possible as $item)
            {
                $mode = \ilObjectLP::getInstance(\ilObject::_lookupObjId($item))->getCurrentMode();
                if($mode !== \ilLPObjSettings::LP_MODE_DEACTIVATED) {
                    $this->createSubXml($item,$progress_filter,$object_types);
                }
            }
        }

        $this->writer->xmlElement(
            'LearningProgressInfoTitle',
            array(),
            ilObject::_lookupTitle($main_lpi_attr['obj_id'])
        );


        $this->writer->xmlElement(
            'LearningProgressInfoDescription',
            array(),
            ilObject::_lookupDescription($main_lpi_attr['obj_id'])
        );

        $this->writer->xmlStartTag('LearningProgressSummary');

        if(in_array(self::PROGRESS_FILTER_ALL, $progress_filter)) {
            foreach (self::$PROGRESS_INFO_TYPES as $filter) {
                if($filter === self::PROGRESS_FILTER_ALL) {
                    continue;
                } else {
                    $this->createObjectStatusElement($filter, $main_lpi_attr['obj_id'], $main_lpi_attr['ref_id']);
                }
            }
        } else {
            foreach ($progress_filter as $filter) {
                $this->createObjectStatusElement($filter, $main_lpi_attr['obj_id'], $main_lpi_attr['ref_id']);
            }
        }

        $this->writer->xmlEndTag('LearningProgressSummary');

        $this->writer->xmlStartTag('UserProgress');

        if(in_array(self::PROGRESS_FILTER_ALL, $progress_filter)) {
            foreach (self::$PROGRESS_INFO_TYPES as $filter) {
                if($filter === self::PROGRESS_FILTER_ALL) {
                    continue;
                } else {
                    $this->createUserElement($filter, $main_lpi_attr['obj_id'], $main_lpi_attr['ref_id']);
                }
            }
        } else {
            foreach ($progress_filter as $filter) {
                $this->createUserElement($filter, $main_lpi_attr['obj_id'], $main_lpi_attr['ref_id']);
            }
        }

        $this->writer->xmlEndTag('UserProgress');


        $this->writer->xmlEndTag('LearningProgressInfo');

        return $this->writer->xmlDumpMem();
    }

}


?>