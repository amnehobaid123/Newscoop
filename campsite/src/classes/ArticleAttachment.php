<?php
/**
 * @package Campsite
 */

/**
 * Includes
 */
require_once($GLOBALS['g_campsiteDir'].'/classes/DatabaseObject.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/SQLSelectClause.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/Attachment.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/CampCacheList.php');
require_once($GLOBALS['g_campsiteDir'].'/template_engine/classes/CampTemplate.php');

/**
 * @package Campsite
 */
class ArticleAttachment extends DatabaseObject {
	var $m_keyColumnNames = array('fk_article_number', 'fk_file_gunid');
	var $m_dbTableName = 'ArticleAttachments';
	var $m_columnNames = array('fk_article_number',
	                           'fk_file_gunid',
	                           'fk_language_id',
	                           'text_embedded',
	                           'content_disposition',
	                           'file_index');


	/**
	 * The article attachment table links together articles with Attachments.
	 *
	 * @param int $p_articleNumber
	 * @param int $p_fileGunId
	 * @return ArticleAttachment
	 */
	public function ArticleAttachment($p_articleNumber = null, $p_fileGunId = null)
	{
		settype($p_articleNumber, 'integer');
		$this->m_data['fk_article_number'] = $p_articleNumber;
		$this->m_data['fk_file_gunid'] = $p_fileGunId;
	} // constructor


	/**
	 * @return int
	 */
	public function getFileGunId()
	{
		return $this->m_data['fk_file_gunid'];
	} // fn getFileGunId


	/**
	 * @return int
	 */
	public function getArticleNumber()
	{
		return $this->m_data['fk_article_number'];
	} // fn getArticleNumber


    /**
     * @return int
     */
    public function getLanguageId()
    {
        return $this->m_data['fk_language_id'];
    } // fn getLanguageId
    

    /**
     * 
     * @return bool
     */
    public function isTextEmbedded()
    {
    	return $this->m_data['text_embedded'] ? true : false;
    } // fn isTextEmbedded


    /**
     * 
     * @return string
     */
    public function getContentDisposition()
    {
    	return $this->m_data['content_disposition'];
    } // fn getContentDisposition


    /**
     * 
     * @return integer
     */
    public function getFileIndex()
    {
    	return $this->m_data['file_index'];
    } // fn getFileIndex


	/**
	 * Link the given file with the given article.
	 *
	 * @param int $p_fileGunId
	 * @param int $p_articleNumber
	 *
	 * @return void
	 */
	public static function AddFileToArticle($p_fileGunId, $p_articleNumber, $p_languageId = null,
	$p_textEmbedded = false, $p_contentDisposition = null, $p_fileIndex = null)
	{
		global $g_ado_db;
		
        $p_fileGunId = "'" . $g_ado_db->escape($p_fileGunId) . "'";
		settype($p_articleNumber, 'integer');
		$p_languageId = is_null($p_languageId) ? 'NULL' : (int)$p_languageId;
		$p_textEmbedded = $p_textEmbedded ? 'true' : 'false';
		$p_contentDisposition = is_null($p_contentDisposition) ? 'NULL'
		: "'" . $g_ado_db->escape($p_contentDisposition) . "'";
		$p_fileIndex = is_null($p_fileIndex) ? 'NULL' : (int)$p_fileIndex;
		
		$queryStr = "INSERT IGNORE INTO ArticleAttachments (fk_article_number, fk_file_gunid, "
		          . "    fk_langugage_id, text_embedded, content_disposition, file_index) "
				  . "VALUES($p_articleNumber, $p_fileGunId, $p_languageId, $p_textEmbedded, "
				  . "$p_contentDisposition, $p_fileIndex)";
		$g_ado_db->Execute($queryStr);
	} // fn AddFileToArticle


	/**
	 * Get all the attachments that belong to this article.
	 * @param int $p_articleNumber
	 * @param int $p_languageId
	 * @return array
	 */
	public static function GetAttachmentsByArticleNumber($p_articleNumber, $p_languageId = null)
	{
		global $g_ado_db;
		$tmpObj = new Attachment();
		$columnNames = implode(',', $tmpObj->getColumnNames());

		if (is_null($p_languageId)) {
			$langConstraint = "FALSE";
		} else {
			$langConstraint = "Attachments.fk_language_id = $p_languageId";
		}
		$queryStr = "SELECT $columnNames"
				  . ' FROM Attachments, ArticleAttachments'
				  . " WHERE ArticleAttachments.fk_article_number = $p_articleNumber"
				  . ' AND ArticleAttachments.fk_file_gunid = Attachments.id'
				  . " AND (Attachments.fk_language_id IS NULL OR $langConstraint)"
				  . ' ORDER BY Attachments.time_created asc, Attachments.file_name asc';
		$rows = $g_ado_db->GetAll($queryStr);
		$returnArray = array();
		if (is_array($rows)) {
			foreach ($rows as $row) {
				$tmpAttachment = new Attachment();
				$tmpAttachment->fetch($row);
				$returnArray[] = $tmpAttachment;
			}
		}
		return $returnArray;
	} // fn GetAttachmentsByArticleNumber


	/**
	 * This is called when an attachment is deleted.
	 * It will disassociate the file from all articles.
	 *
	 * @param int $p_fileGunId
	 * @return void
	 */
	public static function OnAttachmentDelete($p_fileGunId)
	{
		global $g_ado_db;
		
		$queryStr = "DELETE FROM ArticleAttachments "
		          . "WHERE fk_file_gunid = '" . $g_ado_db->escape($p_fileGunId) . "'";
		$g_ado_db->Execute($queryStr);
	} // fn OnAttachmentDelete


	/**
	 * Remove attachment pointers for the given article.
	 * @param int $p_articleNumber
	 * @return void
	 */
	public static function OnArticleDelete($p_articleNumber)
	{
		global $g_ado_db;
		
		settype($p_articleNumber, 'integer');
		$queryStr = 'DELETE FROM ArticleAttachments '
				  . "WHERE fk_article_number = '$p_articleNumber'";
		$g_ado_db->Execute($queryStr);
	} // fn OnArticleDelete


	/**
	 * Copy all the pointers for the given article.
	 * @param int $p_srcArticleNumber
	 * @param int $p_destArticleNumber
	 * @return void
	 */
	public static function OnArticleCopy($p_srcArticleNumber, $p_destArticleNumber)
	{
		global $g_ado_db;
		
		settype($p_srcArticleNumber, 'integer');
		settype($p_destArticleNumber, 'integer');
		$queryStr = 'INSERT IGNORE INTO ArticleAttachments(fk_article_number, fk_file_gunid, '
		          . '    fk_language_id, text_embedded, content_disposition, file_index)'
		          . "SELECT $p_destArticleNumber, fk_file_gunid, "
		          . '    fk_language_id, text_embedded, content_disposition, file_index '
		          . "FROM ArticleAttachments WHERE fk_article_number = $p_srcArticleNumber";
		$g_ado_db->Execute($queryStr);
	} // fn OnArticleCopy


	/**
	 * Remove the linkage between the given attachment and the given article.
	 *
	 * @param int $p_fileGunId
	 * @param int $p_articleNumber
	 *
	 * @return void
	 */
	public static function RemoveAttachmentFromArticle($p_fileGunId, $p_articleNumber)
	{
		global $g_ado_db;
		$queryStr = 'DELETE FROM ArticleAttachments'
					.' WHERE fk_article_number = '.$p_articleNumber
					." AND fk_file_gunid = '".$g_ado_db->escape($p_fileGunId) . "'"
					.' LIMIT 1';
		$g_ado_db->Execute($queryStr);
	} // fn RemoveAttachmentFromArticle


    /**
     * Returns an article attachments list based on the given parameters.
     *
     * @param array $p_parameters
     *    An array of ComparisonOperation objects
     * @param string $p_order
     *    An array of columns and directions to order by
     * @param integer $p_start
     *    The record number to start the list
     * @param integer $p_limit
     *    The offset. How many records from $p_start will be retrieved.
     * @param integer $p_count
     *    The total count of the elements; this count is computed without
     *    applying the start ($p_start) and limit parameters ($p_limit)
     *
     * @return array $articleAttachmentsList
     *    An array of Attachment objects
     */
    public static function GetList(array $p_parameters, $p_order = null,
                                   $p_start = 0, $p_limit = 0, &$p_count, $p_skipCache = false)
    {
        global $g_ado_db;

        if (!$p_skipCache && CampCache::IsEnabled()) {
        	$paramsArray['parameters'] = serialize($p_parameters);
        	$paramsArray['order'] = (is_null($p_order)) ? 'null' : $p_order;
        	$paramsArray['start'] = $p_start;
        	$paramsArray['limit'] = $p_limit;
        	$cacheListObj = new CampCacheList($paramsArray, __METHOD__);
        	$articleAttachmentsList = $cacheListObj->fetchFromCache();
        	if ($articleAttachmentsList !== false
        	&& is_array($articleAttachmentsList)) {
        		return $articleAttachmentsList;
        	}
        }

        $hasArticleNr = false;
        $selectClauseObj = new SQLSelectClause();
        $countClauseObj = new SQLSelectClause();

        // sets the where conditions
        foreach ($p_parameters as $param) {
            $comparisonOperation = self::ProcessParameters($param);
            if (sizeof($comparisonOperation) < 1) {
                break;
            }

            if (strpos($comparisonOperation['left'], 'fk_article_number')) {
                $whereCondition = $comparisonOperation['left'] . ' '
                    . $comparisonOperation['symbol'] . " '"
                    . $comparisonOperation['right'] . "' ";
                $hasArticleNr = true;
            } elseif (strpos($comparisonOperation['left'], 'fk_language_id')) {
                $whereCondition = '('.$comparisonOperation['left'].' IS NULL OR '
                    .$comparisonOperation['left'].' = '.$comparisonOperation['right'].')';
            }
            $selectClauseObj->addWhere($whereCondition);
            $countClauseObj->addWhere($whereCondition);
        }

        // validates whether article number was given
        if ($hasArticleNr === false) {
            CampTemplate::singleton()->trigger_error('missed parameter Article '
                .'Number in statement list_article_attachments');
            return;
        }

        // sets the columns to be fetched
        $tmpAttachment = new Attachment();
		$columnNames = $tmpAttachment->getColumnNames(true);
        foreach ($columnNames as $columnName) {
            $selectClauseObj->addColumn($columnName);
        }
        $countClauseObj->addColumn('COUNT(*)');

        // sets the main table for the query
        $selectClauseObj->setTable($tmpAttachment->getDbTableName());
        $countClauseObj->setTable($tmpAttachment->getDbTableName());
        unset($tmpAttachment);

        // adds the ArticleAttachments join and condition to the query
        $selectClauseObj->addTableFrom('ArticleAttachments');
        $selectClauseObj->addWhere('ArticleAttachments.fk_attachment_id = Attachments.id');
        $countClauseObj->addTableFrom('ArticleAttachments');
        $countClauseObj->addWhere('ArticleAttachments.fk_attachment_id = Attachments.id');

        if (!is_array($p_order)) {
            $p_order = array();
        }

        // sets the order condition if any
        foreach ($p_order as $orderColumn => $orderDirection) {
            $selectClauseObj->addOrderBy($orderColumn . ' ' . $orderDirection);
        }

        // sets the limit
        $selectClauseObj->setLimit($p_start, $p_limit);

        // builds the query and executes it
        $selectQuery = $selectClauseObj->buildQuery();
        $attachments = $g_ado_db->GetAll($selectQuery);
        if (is_array($attachments)) {
        	$countQuery = $countClauseObj->buildQuery();
        	$p_count = $g_ado_db->GetOne($countQuery);

        	// builds the array of attachment objects
        	$articleAttachmentsList = array();
        	foreach ($attachments as $attachment) {
        		$attchObj = new Attachment($attachment['gunid']);
        		if ($attchObj->exists()) {
        			$articleAttachmentsList[] = $attchObj;
        		}
        	}
        } else {
        	$articleAttachmentsList = array();
        	$p_count = 0;
        }
        if (!$p_skipCache && CampCache::IsEnabled()) {
        	$cacheListObj->storeInCache($articleAttachmentsList);
        }

        return $articleAttachmentsList;
    } // fn GetList


    /**
     * Processes a paremeter (condition) coming from template tags.
     *
     * @param array $p_param
     *      The array of parameters
     *
     * @return array $comparisonOperation
     *      The array containing processed values of the condition
     */
    private static function ProcessParameters($p_param)
    {
        $comparisonOperation = array();

        switch (strtolower($p_param->getLeftOperand())) {
        case 'article_number':
            $comparisonOperation['left'] = 'ArticleAttachments.fk_article_number';
            $comparisonOperation['right'] = (int) $p_param->getRightOperand();
            break;
        case 'language_id':
            $comparisonOperation['left'] = 'Attachments.fk_language_id';
            $comparisonOperation['right'] = (int) $p_param->getRightOperand();
            break;
        }

        if (isset($comparisonOperation['left'])) {
            $operatorObj = $p_param->getOperator();
            $comparisonOperation['symbol'] = $operatorObj->getSymbol('sql');
        }

        return $comparisonOperation;
    } // fn ProcessParameters

} // class ArticleAttachment

?>