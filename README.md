yii-CDbExtendedConnection
=========================

Extends CDbExtendedConnection in Yii Framework to allow multiple query in a single statement.
 
  Fast coding version without many testing but maybe it could be useful for someone.
 
  To use this extension:
  1 - Copy this file to your extensions/ directory,
  2 - In config/main.php edit:
       - 'import' => array(
                   ...
                   'application.extensions.CDbExtendedConnection'
          ),
       - 'components' => array(
                   ...
                   'db' => array(
                           ...
                           'class' => 'CDbExtendedConnection',
                    ),
           ),
 
  Example:
  
  $result  = Yii::app()->db->createCommand('SELECT * FROM post; SELECT * FROM comment')->queryMulti();
  
  $results = Yii::app()->db->createCommand('SELECT * FROM post; SELECT * FROM comment')->queryAllMulti();
 
  Output:
  
  $result = array(
   0 => "post"
   1 => "comment"
  )
 
  $results = array(
   0 => array("posts")
   1 => array("comments")
