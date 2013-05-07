<?php
/**
 * NoFramework
 *
 * @author Roman Zaykin <roman@noframework.com>
 * @author Paul Andryushin <job.pablo@yandex.ru>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link http://noframework.com
 */

namespace NoFramework\Storage;

class Mongo extends \NoFramework\Storage
{
	protected $user;
	protected $password;
	protected $host;
	protected $port;
	protected $database;
	protected $is_safe = true;

	public function connect() {
		$out = new \MongoClient('mongodb://'.
            ($this->user?$this->user.($this->password?':'.$this->password:'').'@':'').
            ($this->host?:'localhost').
            ($this->port?':'.$this->port:'').'/'.$this->database);

        #print( spl_object_hash($out) . PHP_EOL );

		return $out->selectDB($this->database);
    }

    public function insert($parameters) {
		extract($parameters);
		$result = $this->connect()->selectCollection($collection)->insert($set, [
            'w' => (int)$this->is_safe
        ]);
        return $set['_id'];
	}

    protected function where(&$where)
    {
        if ( ! isset($where) or ! $where ) {
            return [];
        }

        $out = [];

        foreach ( $where as $field => $value ) {
            if ( is_array($value) ) {
                foreach ( $value as $collation => $collation_value ) {
                    if ( '=' === $collation ) {
                        $value = $collation_value;
                        break;

                    } elseif ( '<' === $collation ) {
                        unset($value[$collation]);
                        $value['$lt'] = $collation_value;

                    } elseif ( '>' === $collation ) {
                        unset($value[$collation]);
                        $value['$gt'] = $collation_value;

                    } elseif ( '<=' === $collation ) {
                        unset($value[$collation]);
                        $value['$lte'] = $collation_value;

                    } elseif ( '>=' === $collation ) {
                        unset($value[$collation]);
                        $value['$gte'] = $collation_value;

                    } elseif ( '<>' === $collation ) {
                        unset($value[$collation]);
                        $value['$ne'] = $collation_value;
                    }
                }
            }
            $out[$field] = $value;
        }

        return $out;
    }

    protected function flatternFields($object, $fields)
    {
        $new_object = [];

        foreach ( $fields as $field ) {
            if ( false !== strpos($field, '.') ) {
                $value = &$object;
                $new_field = [];

                foreach ( explode('.', $field) as $field_part ) {
                    if (!isset($value[$field_part])) {
                        break;
                    }

                    $value = &$value[$field_part];
                    $new_field[] = $field_part;
                }

                $new_object[implode('.', $new_field)] = $value;

            } else {
                $new_object[$field] = isset($object[$field]) ? $object[$field] : null; 
            }
        }

        return $new_object;
    }

	public function group($parameters) {
		extract($parameters);

        if( !isset($keys) || empty($keys) || !isset($initial) || empty($initial) || !isset($reduce) || empty($reduce) )
            throw new \InvalidArgumentException('Bad parametrs form group function'); 

		$out = $this->connect()->selectCollection($collection)->group($keys,$initial,$reduce);
		return $out;
	}

	public function _find($parameters) {
		extract($parameters);
		$out = $this->connect()->selectCollection($collection)->find($this->where($where), isset($fields)?$fields:[]);
		if (isset($sort) and $sort) $out = $out->sort($sort);
		if (isset($skip) and $skip) $out = $out->skip($skip);
		if (isset($limit) and $limit) $out = $out->limit($limit);
        if (isset($timeout)) $out = $out->timeout($timeout);
        if (isset($immortal)) $out = $out->immortal($immortal);
		return $out;
	}

	public function find($parameters) {
        $out = $this->_find($parameters);

        if ( isset($parameters['fields']) ) {
            $flattern = [];

            foreach( $out as $key => $row ) {
                $flattern[] = $this->flatternFields($row, $parameters['fields']);
            }

            return $flattern;
        }

		return $out;
	}

	public function walk($parameters, $closure) {
		foreach( $this->_find($parameters) as $row ) {
            if ( isset($parameters['fields']) ) {
                $closure($this->flatternFields($row, $parameters['fields']));
            } else {
                $closure($row);
            }
        }

		return $this;
	}

	public function findOne($parameters) {
		extract($parameters);
        $fields = isset($fields) ? $fields : [];
		$result =  $this->connect()->selectCollection($collection)->findOne($this->where($where), $fields);

        if ( ! $result ) {
            return false;
        }

        if ( $fields ) {
            $result = $this->flatternFields($result, $fields);
        }
        return 1 === count($fields) ? (isset($result[$fields[0]]) ? $result[$fields[0]] : false) : $result;
	}

	public function update($parameters) {
		extract($parameters);

		return $this->connect()->selectCollection($collection)->update($this->where($where), ['$set' => $set], [
            'w' => (int)$this->is_safe,
            'upsert' => isset($upsert) ? (bool)$upsert : false,
            'multiple' => true
        ]);
	}

	public function remove($parameters) {
		extract($parameters);
		return $this->connect()->selectCollection($collection)->remove($this->where($where), [
            'w' => (int)$this->is_safe,
            'justOne' => isset($justOne) ? (bool)$justOne : false
        ]);
	}

	public function count($parameters) {
		extract($parameters);
		return $this->connect()->selectCollection($collection)->count($this->where($where));
	}

	public function listCollections() {
		$out = [];
		$connecttion = $this->connect();
        foreach ($connection->listCollections() as $collection) $out[] =  $collection->getName();
		return $out;
	}

    public function fromUnixTimestamp($timestamp) {
        return new \MongoDate($timestamp);
    }

    public function toUnixTimestamp($timestamp) {
        return $timestamp->sec;
    }

    public function newId($id = null) {
        return new \MongoId($id);
    }
}

