<?php

namespace Vhmis\Db\MySQL;

class Model
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
     *
     * @var array
     */
    protected $entityKey = array();

    /**
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityInsert = array();

    /**
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityUpdate = array();

    /**
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityDelete = array();

    /**
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityHasInserted = array();

    /**
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityHasUpdated = array();

    /**
     *
     * @var \Vhmis\Db\MySQL\Entity[]
     */
    protected $entityHasDeleted = array();

    /**
     * Khởi tạo
     *
     * @param \Vhmis\Db\MySQL\Adapter $adapter
     */
    public function __construct(Adapter $adapter = null)
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
    public function setAdapter(Adapter $adapter)
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
        $this->entityClass = $this->class . '\\Entity';

        $class = explode('\\', $this->class);
        if ($this->table == '') {
            $this->table = $this->camelCaseToUnderscore($class[count($class) - 1]);
        }

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

    public function find($where)
    {
        if (!is_array($where)) {
            return array();
        }

        if (count($where) == 0) {
            return $this->findAll();
        }

        $sql = array();
        $bindData = array();
        $pos = 1;

        foreach ($where as $field => $value) {
            $sql[] = $field . ' = ?';
            $bindData[$pos] = $value;
            $pos++;
        }

        $sql = 'select * from `' . $this->table . '` where ' . implode(', ', $sql);

        $statement = new Statement;
        $result = $statement->setAdapter($this->adapter)->setParameters($bindData)->setSql($sql)->execute();

        $data = array();

        while ($row = $result->next()) {
            $data[] = $this->fillRowToEntityClass($row);
        }

        return $data;
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
                $update[] = $field . ' = ?';
                $bindData[$pos] = $value;
                $pos++;
            }

            foreach ($where as $field => $value) {
                $sqlWhere[] = $field . ' = ?';
                $bindData[$pos] = $value;
                $pos++;
            }

            $sql = 'update `' . $this->table . '` set ';
            $sql .= implode(', ', $update);
            $sql .= ' where ';
            $sql .= implode(', ', $sqlWhere);
        } else {
            return 0;
        }

        $statement = new Statement;
        $result = $statement->setAdapter($this->adapter)->setParameters($bindData)->setSql($sql)->execute();
        return $result->count();
    }

    public function insertQueue($entity)
    {
        if (!($entity instanceof $this->entityClass)) {
            return $this;
        }

        $methodGetIdKey = 'get' . $this->underscoreToCamelCase($this->idKey, true);
        if ($entity->$methodGetIdKey() != null) {
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

    public function updateQueue($entity)
    {
        if (!($entity instanceof $this->entityClass)) {
            return $this;
        }

        $methodGetIdKey = 'get' . $this->underscoreToCamelCase($this->idKey, true);
        if ($entity->$methodGetIdKey() === null) {
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

    public function deleteQueue($entity)
    {
        if (!($entity instanceof $this->entityClass)) {
            return $this;
        }

        $methodGetIdKey = 'get' . $this->underscoreToCamelCase($this->idKey, true);
        if ($entity->$methodGetIdKey() === null) {
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

    protected function doInsert()
    {
        foreach ($this->entityInsert as $id => $entity) {
            $prepareSQL = $entity->insertSQL();

            $stm = $this->adapter->createStatement('insert into ' . $this->table . ' ' . $prepareSQL['sql'],
                $prepareSQL['param']);
            $res = $stm->execute();
            if ($res->getLastValue()) {
                $setId = 'set' . $this->underscoreToCamelCase($this->idKey, true);
                $entity->$setId($res->getLastValue());
            }

            $entity->setNewValue();
            $this->entityHasInserted[$id] = $entity;

            unset($this->entityKey[$id]);
            unset($this->entityInsert[$id]);
        }
    }

    protected function doUpdate()
    {
        foreach ($this->entityUpdate as $id => $entity) {
            $prepareSQL = $entity->updateSQL();
            $getId = 'get' . $this->underscoreToCamelCase($this->idKey, true);
            $prepareSQL['param'][':' . $this->idKey] = $entity->$getId();

            $stm = $this->adapter->createStatement('update ' . $this->table . ' set ' . $prepareSQL['sql'] . ' where ' . $this->idKey . ' = :' . $this->idKey,
                $prepareSQL['param']);
            $res = $stm->execute();

            $entity->setNewValue();

            $this->entityHasUpdated[$id] = $entity;

            unset($this->entityKey[$id]);
            unset($this->entityUpdate[$id]);
        }
    }

    protected function doDelete()
    {
        foreach ($this->entityDelete as $id => $entity) {
            $getId = 'get' . $this->underscoreToCamelCase($this->idKey, true);
            $stm = $this->adapter->createStatement('delete from ' . $this->table . ' where ' . $this->idKey . ' = ?',
                array(1 => $entity->$getId()));
            $res = $stm->execute();
            $entity->setDeleted(true);
            $this->entityHasDeleted[$id] = $entity;
            unset($this->entityKey[$id]);
            unset($this->entityDelete[$id]);
        }
    }

    protected function rollbackInsert()
    {
        foreach ($this->entityHasInserted as $id => $entity) {
            $setId = 'set' . $this->underscoreToCamelCase($this->idKey, true);
            $entity->rollback()->$setId(null);

            unset($this->entityHasInserted[$id]);
        }
    }

    protected function rollbackUpdate()
    {
        foreach ($this->entityHasUpdated as $id => $entity) {
            $entity->rollback();

            unset($this->entityHasUpdated[$id]);
        }
    }

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
