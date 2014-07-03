<?php
/**
 * CDbExtendedConnection class file.
 *
 * @author Javier Juan <javijuol@gmail.com>
 * @link https://github.com/javijuol/yii-CDbExtendedConnection
 * @version 0.1
 * @package extensions\CDbExtendedConnection
 *
 */

/**
 *
 * Extends CDbExtendedConnection in Yii Framework to allow multiple query in a single statement.
 *
 * Fast coding version without many testing but maybe it could be useful for someone.
 *
 * To use this extension:
 * 1 - Copy this file to your extensions/ directory,
 * 2 - In config/main.php edit:
 *      - 'import' => array(
 *                  ...
 *                  'application.extensions.CDbExtendedConnection'
 *         ),
 *      - 'components' => array(
 *                  ...
 *                  'db' => array(
 *                          ...
 *                          'class' => 'CDbExtendedConnection',
 *                   ),
 *          ),
 *
 * Example:
 * $result  = Yii::app()->db->createCommand('SELECT * FROM post; SELECT * FROM comment')->queryMulti();
 * $results = Yii::app()->db->createCommand('SELECT * FROM post; SELECT * FROM comment')->queryAllMulti();
 *
 * Output:
 * $result = array(
 *  0 => "post"
 *  1 => "comment"
 * )
 *
 * $results = array(
 *  0 => array("posts")
 *  1 => array("comments")
 * )
 */


class CDbExtendedConnection extends CDbConnection{

    public function createCommand($query=null)
    {
        $this->setActive(true);
        return new CDbMultiCommand($this,$query);
    }
}

class CDbMultiCommand extends CDbCommand {

    private $_connection;
    private $_statement;
    private $_fetchMode = array(PDO::FETCH_ASSOC);


    public function queryMulti($fetchAssociative=true,$params=array())
    {
        $this->_connection = $this->getConnection();
        $this->_statement=$this->getConnection()->getPdoInstance()->prepare($this->getText());
        return $this->queryInternal('fetch',$fetchAssociative ? $this->_fetchMode : PDO::FETCH_NUM, $params);
    }

    public function queryAllMulti($fetchAssociative=true,$params=array())
    {
        $this->_connection = $this->getConnection();
        $this->_statement=$this->getConnection()->getPdoInstance()->prepare($this->getText());
        return $this->queryInternal('fetchAll',$fetchAssociative ? $this->_fetchMode : PDO::FETCH_NUM, $params);
    }

    private function queryInternal($method,$mode,$params=array())
    {
        $params=array_merge($this->params,$params);

        if($this->_connection->enableParamLogging && ($params)!==array())
        {
            $p=array();
            foreach($params as $name=>$value)
                $p[$name]=$name.'='.var_export($value,true);
            $par='. Bound with '.implode(', ',$p);
        }
        else
            $par='';

        Yii::trace('Querying SQL: '.$this->getText().$par,'system.db.CDbCommand');

        if($this->_connection->queryCachingCount>0 && $method!==''
            && $this->_connection->queryCachingDuration>0
            && $this->_connection->queryCacheID!==false
            && ($cache=Yii::app()->getComponent($this->_connection->queryCacheID))!==null)
        {
            $this->_connection->queryCachingCount--;
            $cacheKey='yii:dbquery'.':'.$method.':'.$this->_connection->connectionString.':'.$this->_connection->username;
            $cacheKey.=':'.$this->getText().':'.serialize($params);
            if(($result=$cache->get($cacheKey))!==false)
            {
                Yii::trace('Query result found in cache','system.db.CDbCommand');
                return $result[0];
            }
        }

        try
        {
            if($this->_connection->enableProfiling)
                Yii::beginProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');

            $this->prepare();
            if($params===array())
                $this->_statement->execute();
            else
                $this->_statement->execute($params);

            if($method==='')
                $result=new CDbDataReader($this);
            else
            {
                $mode=(array)$mode;
                do {
                    call_user_func_array(array($this->_statement, 'setFetchMode'), $mode);
                    $result[]=$this->_statement->$method();
                } while ($this->_statement->nextRowset());
                $this->_statement->closeCursor();
            }

            if($this->_connection->enableProfiling)
                Yii::endProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');

            if(isset($cache,$cacheKey))
                $cache->set($cacheKey, array($result), $this->_connection->queryCachingDuration, $this->_connection->queryCachingDependency);

            return $result;
        }
        catch(Exception $e)
        {
            if($this->_connection->enableProfiling)
                Yii::endProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');

            $errorInfo=$e instanceof PDOException ? $e->errorInfo : null;
            $message=$e->getMessage();
            Yii::log(Yii::t('yii','CDbCommand::{method}() failed: {error}. The SQL statement executed was: {sql}.',
                array('{method}'=>$method, '{error}'=>$message, '{sql}'=>$this->getText().$par)),CLogger::LEVEL_ERROR,'system.db.CDbCommand');

            if(YII_DEBUG)
                $message.='. The SQL statement executed was: '.$this->getText().$par;

            throw new CDbException(Yii::t('yii','CDbCommand failed to execute the SQL statement: {error}',
                array('{error}'=>$message)),(int)$e->getCode(),$errorInfo);
        }
    }
}
