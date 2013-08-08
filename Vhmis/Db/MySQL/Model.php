<?php

namespace Vhmis\Db\MySQL;

use \Vhmis\Db\AdapterInterface;
use \Vhmis\Db\ModelInterface;

class Model implements ModelInterface
{
    /**
     * Tên class Model (bắt đầu bằng \)
     *
     * @var string
     */
    protected $class;

    /**
     * Tên class Entity
     *
     * @var string
     */
    protected $entityClass;

    /**
     * Tên bảng ứng với model
     *
     * @var string
     */
    protected $table;

    /**
     * Adapter
     *
     * @var \Vhmis\Db\MySQL\Adapter
     */
    protected $adapter = null;

    /**
     * Tên trường primary key
     *
     * @var string
     */
    protected $idKey = 'id';

    /**
     * Danh sách các key của Entity chờ cập nhật (thêm, xóa, sửa) lên CSDL
     *
     * @var array
     */
    protected $entityKey = array();

    /**
     * Danh sách các Entity chờ được insert
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityInsert = array();

    /**
     * Danh sách các Entity chờ được update
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityUpdate = array();

    /**
     * Danh sách các Entity chờ được delete
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityDelete = array();

    /**
     * Danh sách các Entity đã được insert vào CSDL
     * Dùng trong quá trình rollback nếu có lỗi xảy ra trong toàn bố quá trình cập nhật CSDL
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityHasInserted = array();

    /**
     * Danh sách các Entity đã được update lên CSDL
     * Dùng trong quá trình rollback nếu có lỗi xảy ra trong toàn bố quá trình cập nhật CSDL
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityHasUpdated = array();

    /**
     * Danh sách các Entity đã được delete khỏi CSDL
     * Dùng trong quá trình rollback nếu có lỗi xảy ra trong toàn bố quá trình cập nhật CSDL
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityHasDeleted = array();

    /**
     * Khởi tạo
     *
     * @param \Vhmis\Db\AdapterInterface $adapter
     */
    public function __construct(AdapterInterface $adapter = null)
    {
        if ($adapter instanceof Adapter) {
            $this->setAdapter($adapter);
        }
    }

    /**
     * Thiết lập adapter
     *
     * @param \Vhmis\Db\MySQL\Adapter $adapter
     * @return \Vhmis\Db\MySQL\Model
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this->init();
    }

    /**
     * Khởi tạo model sau khi thiết lập adapter thành công
     *
     * @return \Vhmis\Db\MySQL\Model
     * @throws \Exception
     */
    public function init()
    {
        if (!$this->adapter instanceof Adapter) {
            throw new \Exception('Need an Adapter');
        }

        $this->class = '\\' . get_class($this);

        $class = explode('\\', $this->class);
        $table = $class[count($class) - 1];

        if ($this->table == '') {
            $this->table = $this->camelCaseToUnderscore($table);
        }

        $class[count($class) - 1] = $table . 'Entity';

        $this->entityClass = implode('\\', $class);

        return $this;
    }

    /**
     * Tìm tất cả dữ liệu
     *
     * - Nếu bảng chưa có giá trị thì sẽ trả về mảng rỗng
     * - Nếu bảng đã có dữ liệu thì sẽ trả về mảng chứa các đối tượng Entity tương ứng với Model
     *
     * @return array
     */
    public function findAll()
    {
        $sql = 'select * from `' . $this->table . '`';

        $statement = new Statement;
        $result = $statement->setAdapter($this->adapter)->setSql($sql)->execute();

        $data = array();

        while ($row = $result->next()) {
            $data[] = $this->fillRowToEntityClass($row);
        }

        return $data;
    }

    /**
     * Tìm theo primany key
     *
     * @param string $id
     * @return null
     */
    public function findById($id)
    {
        $sql = 'select * from `' . $this->table . '` where ' . $this->idKey . ' = ?';

        $statement = new Statement;
        $result = $statement->setAdapter($this->adapter)->setParameters(array(1 => $id))->setSql($sql)->execute();

        if ($row = $result->current()) {
            return $this->fillRowToEntityClass($row);
        } else {
            return null;
        }
    }

    public function find($where = array(), $order = array(), $skip = 0, $limit = 0)
    {
        $bindData = array();
        if (is_array($where) && count($where) != 0) {
            $sql = array();
            $pos = 1;

            foreach ($where as $w) {
                $field = $w[0];
                $operator = $w[1];
                $value = $w[2];

                // Try to camelCaseToUnderscore field name
                $field = $this->camelCaseToUnderscore($field);

                // Prepare query
                $sql_temp = '';
                if ($operator == 'in') {
                    $sql_temp = $field . ' in ';
                } else {
                    $sql[] = $field . ' ' . $operator . ' ?';
                }

                // Bind value
                if ($operator == 'in') {
                    if (is_array($value)) {
                        $values = array();
                        foreach ($value as $v) {
                            if (is_numeric($v)) {
                                $values[] = $v;
                            } else {
                                $values[] = $this->adapter->qoute($v);
                            }
                        }
                        $sql_temp .= '(' . implode(', ', $values) . ')';
                        $sql[] = $sql_temp;
                    } else {
                        throw new \Exception('Value for IN must be an array');
                    }
                } else {
                    $bindData[$pos] = $value;
                    // Count
                    $pos++;
                }
            }

            $sql = 'select * from `' . $this->table . '` where ' . implode(' and ', $sql);
        } else {
            $sql = 'select * from `' . $this->table . '`';
        }

        if (is_array($order)) {
            $orderby = array();

            foreach ($order as $field => $or) {
                $field = $this->camelCaseToUnderscore($field);
                $or = $or === 'asc' ? 'asc' : 'desc';
                $orderby[] = $field . ' ' . $or;
            }

            if (count($orderby) > 0) {
                $sql .= ' order by ' . implode(', ', $orderby);
            }
        }

        if ($skip != 0 || $limit != 0) {
            $sql .= ' limit ' . $skip . ', ' . $limit;
        }

        $statement = new Statement;
        $result = $statement->setAdapter($this->adapter)->setParameters($bindData)->setSql($sql)->execute();

        $data = array();

        while ($row = $result->next()) {
            $data[] = $this->fillRowToEntityClass($row);
        }

        return $data;
    }

    public function findOne($where, $order = array())
    {
        $result = $this->find($where, $order, 0, 1);

        if (count($result) === 0)
            return null;
        else
            return $result[0];
    }

    /**
     * Cập nhật
     */
    public function update($where, $data = null)
    {
        if (is_array($where) && is_array($data) && count($data) > 0) {
            $sqlWhere = array();
            $update = array();
            $bindData = array();
            $pos = 1;

            foreach ($data as $field => $value) {
                $field = $this->camelCaseToUnderscore($field);

                $update[] = $field . ' = ?';
                $bindData[$pos] = $value;
                $pos++;
            }

            foreach ($where as $w) {
                $field = $w[0];
                $operator = $w[1];
                $value = $w[2];

                // Try to camelCaseToUnderscore field name
                $field = $this->camelCaseToUnderscore($field);

                // Prepare query
                $sql_temp = '';
                if ($operator == 'in') {
                    $sql_temp = $field . ' in ';
                } else {
                    $sqlWhere[] = $field . ' ' . $operator . ' ?';
                }

                // Bind value
                if ($operator == 'in') {
                    if (is_array($value)) {
                        $values = array();
                        foreach ($value as $v) {
                            if (is_numeric($v)) {
                                $values[] = $v;
                            } else {
                                $values[] = $this->adapter->qoute($v);
                            }
                        }
                        $sql_temp .= '(' . implode(', ', $values) . ')';
                        $sqlWhere[] = $sql_temp;
                    } else {
                        throw new \Exception('Value for IN must be an array');
                    }
                } else {
                    $bindData[$pos] = $value;
                    // Count
                    $pos++;
                }
            }

            $sql = 'update `' . $this->table . '` set ';
            $sql .= implode(', ', $update);
            $sql .= ' where ';
            $sql .= implode(' and ', $sqlWhere);
        } else {
            return 0;
        }

        $statement = new Statement;
        $result = $statement->setAdapter($this->adapter)->setParameters($bindData)->setSql($sql)->execute();
        return $result->count();
    }

    /**
     * Thêm vào danh sách đợi 1 Entity cần insert vào CSDL
     *
     * @param \Vhmis\Db\MySQL\Entity $entity
     * @return \Vhmis\Db\MySQL\Model
     */
    public function insertQueue($entity)
    {
        if (!($entity instanceof $this->entityClass)) {
            return $this;
        }

        $methodGetIdKey = $this->underscoreToCamelCase($this->idKey, false);
        if ($entity->$methodGetIdKey != null) {
            return $this;
        }

        $id = spl_object_hash($entity);

        if (isset($this->entityKey[$id])) {
            return $this;
        }

        $this->entityKey[$id] = 1;
        $this->entityInsert[$id] = $entity;

        return $this;
    }

    /**
     * Thêm vào danh sách đợi 1 Entity cần update lên CSDL
     *
     * @param \Vhmis\Db\MySQL\Entity $entity
     * @return \Vhmis\Db\MySQL\Model
     */
    public function updateQueue($entity)
    {
        if (!($entity instanceof $this->entityClass)) {
            return $this;
        }

        $methodGetIdKey = $this->underscoreToCamelCase($this->idKey);
        if ($entity->$methodGetIdKey === null) {
            return $this;
        }

        $id = spl_object_hash($entity);

        if (isset($this->entityKey[$id])) {
            return $this;
        }

        if ($entity->isChanged() === false) {
            return $this;
        }

        $this->entityKey[$id] = 1;
        $this->entityUpdate[$id] = $entity;

        return $this;
    }

    /**
     * Thêm vào danh sách đợi 1 Entity cần delete khỏi CSDL
     *
     * @param \Vhmis\Db\MySQL\Entity $entity
     * @return \Vhmis\Db\MySQL\Model
     */
    public function deleteQueue($entity)
    {
        if (!($entity instanceof $this->entityClass)) {
            return $this;
        }

        $methodGetIdKey = $this->underscoreToCamelCase($this->idKey);
        if ($entity->$methodGetIdKey === null) {
            return $this;
        }

        $id = spl_object_hash($entity);

        if (isset($this->entityKey[$id])) {
            return $this;
        }

        $this->entityKey[$id] = 1;
        $this->entityDelete[$id] = $entity;

        return $this;
    }

    /**
     * Thực hiện các thay đổi trên CSDL
     *
     * @return boolean
     */
    public function flush()
    {
        if (empty($this->entityKey)) {
            return true;
        }

        $this->adapter->beginTransaction();

        try {
            $this->doInsert();
            $this->doUpdate();
            $this->doDelete();

            $this->adapter->commit();

            $this->entityHasInserted = array();
            $this->entityHasUpdated = array();
            $this->entityHasDeleted = array();

            return true;
        } catch (\PDOException $e) {
            $this->adapter->rollback();

            $this->rollbackInsert();
            $this->rollbackUpdate();
            $this->rollbackDelete();

            return false;
        }
    }

    /**
     * Thực hiện việc insert các entity vào CSDL
     */
    protected function doInsert()
    {
        foreach ($this->entityInsert as $id => $entity) {
            $prepareSQL = $entity->insertSQL();

            $stm = $this->adapter->createStatement('insert into ' . $this->table . ' ' . $prepareSQL['sql'], $prepareSQL['param']);
            $res = $stm->execute();
            if ($res->getLastValue()) {
                $setId = $this->underscoreToCamelCase($this->idKey);
                $entity->$setId = $res->getLastValue();
            }

            $entity->setNewValue();
            $this->entityHasInserted[$id] = $entity;

            unset($this->entityKey[$id]);
            unset($this->entityInsert[$id]);
        }
    }

    /**
     * Thực hiện việc update các entity lên CSDL
     */
    protected function doUpdate()
    {
        foreach ($this->entityUpdate as $id => $entity) {
            $prepareSQL = $entity->updateSQL();
            $getId = $this->underscoreToCamelCase($this->idKey);
            $prepareSQL['param'][':' . $this->idKey] = $entity->$getId;

            $stm = $this->adapter->createStatement('update ' . $this->table . ' set ' . $prepareSQL['sql'] . ' where ' . $this->idKey . ' = :' . $this->idKey, $prepareSQL['param']);
            $res = $stm->execute();

            $entity->setNewValue();

            $this->entityHasUpdated[$id] = $entity;

            unset($this->entityKey[$id]);
            unset($this->entityUpdate[$id]);
        }
    }

    /**
     * Thực hiện việc delete các entity khỏi CSDL
     */
    protected function doDelete()
    {
        foreach ($this->entityDelete as $id => $entity) {
            $getId = $this->underscoreToCamelCase($this->idKey);
            $stm = $this->adapter->createStatement('delete from ' . $this->table . ' where ' . $this->idKey . ' = ?', array(1 => $entity->$getId));
            $res = $stm->execute();
            $entity->setDeleted(true);
            $this->entityHasDeleted[$id] = $entity;
            unset($this->entityKey[$id]);
            unset($this->entityDelete[$id]);
        }
    }

    /**
     * Phục hồi các entity đã được insert lại như ban đầu nếu quá trình cập nhật CSDL bị lỗi
     */
    protected function rollbackInsert()
    {
        foreach ($this->entityHasInserted as $id => $entity) {
            $setId = $this->underscoreToCamelCase($this->idKey);
            $entity->rollback()->$setId = null;

            unset($this->entityHasInserted[$id]);
        }
    }

    /**
     * Phục hồi các entity đã được update lại như ban đầu nếu quá trình cập nhật CSDL bị lỗi
     */
    protected function rollbackUpdate()
    {
        foreach ($this->entityHasUpdated as $id => $entity) {
            $entity->rollback();

            unset($this->entityHasUpdated[$id]);
        }
    }

    /**
     * Phục hồi các entity đã được xóa lại như ban đầu nếu quá trình cập nhật CSDL bị lỗi
     */
    protected function rollbackDelete()
    {
        foreach ($this->entityHasDeleted as $id => $entity) {
            $entity->setDeleted(false);

            unset($this->entityHasDeleted[$id]);
        }
    }

    /**
     * Tạo một đối tượng Entity từ một kết quả trả về ở cơ sở dữ liệu
     *
     * @param array $row
     * @return
     */
    protected function fillRowToEntityClass($row)
    {
        $entity = new $this->entityClass($row);

        return $entity;
    }

    /**
     * Chuyển đổi chuỗi dạng camelCase sang Underscore
     *
     * @param string $str
     * @return string
     */
    protected function camelCaseToUnderscore($str)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
    }

    /**
     * Chuyển đổi chuỗi dạng Underscore sang camelCase
     *
     * @param string $str
     * @param bool $ucfirst
     * @return string
     */
    protected function underscoreToCamelCase($str, $ucfirst = false)
    {
        $parts = explode('_', $str);
        $parts = $parts ? array_map('ucfirst', $parts) : array($str);
        $parts[0] = $ucfirst ? ucfirst($parts[0]) : lcfirst($parts[0]);
        return implode('', $parts);
    }
}
