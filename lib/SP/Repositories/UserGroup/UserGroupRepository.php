<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Repositories\UserGroup;

use SP\Core\Acl\Acl;
use SP\Core\Exceptions\SPException;
use SP\DataModel\ItemSearchData;
use SP\DataModel\UserGroupData;
use SP\Log\Log;
use SP\Repositories\Repository;
use SP\Repositories\RepositoryItemInterface;
use SP\Repositories\RepositoryItemTrait;
use SP\Storage\DbWrapper;
use SP\Storage\QueryData;

/**
 * Class UserGroupRepository
 *
 * @package SP\Repositories\UserGroup
 */
class UserGroupRepository extends Repository implements RepositoryItemInterface
{
    use RepositoryItemTrait;

    /**
     * Deletes an item
     *
     * @param $id
     * @return int
     * @throws SPException
     */
    public function delete($id)
    {
        if ($this->checkInUse($id)) {
            throw new SPException(__u('Grupo en uso'), SPException::WARNING);
        }

        $query = /** @lang SQL */
            'DELETE FROM UserGroup WHERE id = ? LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($id);
        $Data->setOnErrorMessage(__u('Error al eliminar el grupo'));

        DbWrapper::getQuery($Data, $this->db);

        return $Data->getQueryNumRows();
    }

    /**
     * Checks whether the item is in use or not
     *
     * @param $id int
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkInUse($id)
    {
        $query = /** @lang SQL */
            'SELECT userGroupId
            FROM User WHERE userGroupId = ?
            UNION ALL
            SELECT userGroupId
            FROM Account WHERE userGroupId = ?';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParams(array_fill(0, 2, (int)$id));

        DbWrapper::getQuery($Data, $this->db);

        return $Data->getQueryNumRows() > 0;
    }

    /**
     * Checks whether the item is in use or not
     *
     * @param $id int
     * @return array
     */
    public function getUsage($id)
    {
        $query = /** @lang SQL */
            'SELECT userGroupId, "User" as ref
            FROM User WHERE userGroupId = ?
            UNION ALL
            SELECT userGroupId, "UserGroup" as ref
            FROM UserToUserGroup WHERE userGroupId = ?
            UNION ALL
            SELECT userGroupId, "AccountToUserGroup" as ref
            FROM AccountToUserGroup WHERE userGroupId = ?
            UNION ALL
            SELECT userGroupId, "Account" as ref
            FROM Account WHERE userGroupId = ?';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParams(array_fill(0, 4, (int)$id));

        return DbWrapper::getResultsArray($Data, $this->db);
    }

    /**
     * Returns the item for given id
     *
     * @param int $id
     * @return mixed
     */
    public function getById($id)
    {
        $query = /** @lang SQL */
            'SELECT id, name, description FROM UserGroup WHERE id = ? LIMIT 1';

        $Data = new QueryData();
        $Data->setMapClassName(UserGroupData::class);
        $Data->setQuery($query);
        $Data->addParam($id);

        return DbWrapper::getResults($Data, $this->db);
    }

    /**
     * Returns the item for given name
     *
     * @param string $name
     * @return UserGroupData
     */
    public function getByName($name)
    {
        $query = /** @lang SQL */
            'SELECT id, name, description FROM UserGroup WHERE name = ? LIMIT 1';

        $Data = new QueryData();
        $Data->setMapClassName(UserGroupData::class);
        $Data->setQuery($query);
        $Data->addParam($name);

        return DbWrapper::getResults($Data, $this->db);
    }

    /**
     * Returns all the items
     *
     * @return mixed
     */
    public function getAll()
    {
        $query = /** @lang SQL */
            'SELECT id, name, description FROM UserGroup ORDER BY name';

        $Data = new QueryData();
        $Data->setMapClassName(UserGroupData::class);
        $Data->setQuery($query);

        return DbWrapper::getResultsArray($Data, $this->db);
    }

    /**
     * Returns all the items for given ids
     *
     * @param array $ids
     * @return array
     */
    public function getByIdBatch(array $ids)
    {
        if (count($ids) === 0) {
            return [];
        }

        $query = /** @lang SQL */
            'SELECT id, name, description FROM UserGroup WHERE id IN (' . $this->getParamsFromArray($ids) . ')';

        $Data = new QueryData();
        $Data->setMapClassName(UserGroupData::class);
        $Data->setQuery($query);
        $Data->setParams($ids);

        return DbWrapper::getResultsArray($Data, $this->db);
    }

    /**
     * Deletes all the items for given ids
     *
     * @param array $ids
     * @return int
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function deleteByIdBatch(array $ids)
    {
        $query = /** @lang SQL */
            'DELETE FROM UserGroup WHERE id IN (' . $this->getParamsFromArray($ids) . ')';

        $Data = new QueryData();
        $Data->setMapClassName(UserGroupData::class);
        $Data->setQuery($query);
        $Data->setParams($ids);

        DbWrapper::getQuery($Data, $this->db);

        return $Data->getQueryNumRows();
    }

    /**
     * Searches for items by a given filter
     *
     * @param ItemSearchData $SearchData
     * @return mixed
     */
    public function search(ItemSearchData $SearchData)
    {
        $Data = new QueryData();
        $Data->setMapClassName(UserGroupData::class);
        $Data->setSelect('id, name, description');
        $Data->setFrom('UserGroup');
        $Data->setOrder('name');

        if ($SearchData->getSeachString() !== '') {
            $Data->setWhere('name LIKE ? OR description LIKE ?');

            $search = '%' . $SearchData->getSeachString() . '%';
            $Data->addParam($search);
            $Data->addParam($search);
        }

        $Data->setLimit('?,?');
        $Data->addParam($SearchData->getLimitStart());
        $Data->addParam($SearchData->getLimitCount());

        DbWrapper::setFullRowCount();

        $queryRes = DbWrapper::getResultsArray($Data, $this->db);

        $queryRes['count'] = $Data->getQueryNumRows();

        return $queryRes;
    }

    /**
     * Creates an item
     *
     * @param UserGroupData $itemData
     * @return int
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function create($itemData)
    {
        if ($this->checkDuplicatedOnAdd($itemData)) {
            throw new SPException(__u('Nombre de grupo duplicado'), SPException::INFO);
        }

        $query = /** @lang SQL */
            'INSERT INTO UserGroup SET name = ?, description = ?';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($itemData->getName());
        $Data->addParam($itemData->getDescription());
        $Data->setOnErrorMessage(__u('Error al crear el grupo'));

        DbWrapper::getQuery($Data, $this->db);

        return $this->db->getLastId();
    }

    /**
     * Checks whether the item is duplicated on adding
     *
     * @param UserGroupData $itemData
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkDuplicatedOnAdd($itemData)
    {
        $query = /** @lang SQL */
            'SELECT name FROM UserGroup WHERE UPPER(name) = UPPER(?)';
        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($itemData->getName());

        DbWrapper::getQuery($Data, $this->db);

        return $Data->getQueryNumRows() > 0;
    }

    /**
     * Updates an item
     *
     * @param UserGroupData $itemData
     * @return mixed
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function update($itemData)
    {
        if ($this->checkDuplicatedOnUpdate($itemData)) {
            throw new SPException(__u('Nombre de grupo duplicado'), SPException::INFO);
        }

        $query = /** @lang SQL */
            'UPDATE UserGroup SET name = ?, description = ? WHERE id = ? LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($itemData->getName());
        $Data->addParam($itemData->getDescription());
        $Data->addParam($itemData->getId());
        $Data->setOnErrorMessage(__u('Error al actualizar el grupo'));

        DbWrapper::getQuery($Data, $this->db);

        return $this;
    }

    /**
     * Checks whether the item is duplicated on updating
     *
     * @param UserGroupData $itemData
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkDuplicatedOnUpdate($itemData)
    {
        $query = /** @lang SQL */
            'SELECT name FROM UserGroup WHERE UPPER(name) = UPPER(?) AND id <> ?';
        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($itemData->getName());
        $Data->addParam($itemData->getId());

        DbWrapper::getQuery($Data, $this->db);

        return $Data->getQueryNumRows() > 0;
    }

    /**
     * Logs group action
     *
     * @param int $id
     * @param int $actionId
     * @return \SP\Core\Messages\LogMessage
     */
    public function logAction($id, $actionId)
    {
        $query = /** @lang SQL */
            'SELECT name FROM UserGroup WHERE id = ? LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($id);

        $usergroup = DbWrapper::getResults($Data, $this->db);

        $Log = new Log();
        $LogMessage = $Log->getLogMessage();
        $LogMessage->setAction(Acl::getActionInfo($actionId));
        $LogMessage->addDetails(__u('Grupo'), $usergroup->name);
        $LogMessage->addDetails(__u('ID'), $id);
        $Log->writeLog();

        return $LogMessage;
    }
}