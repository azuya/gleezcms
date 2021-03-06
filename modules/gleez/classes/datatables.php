<?php
/**
 * Gleez jQuery DataTables support
 *
 * @package    Gleez\Datatables
 * @author     Gleez Team
 * @version    2.0
 * @copyright  (c) 2011-2014 Gleez Technologies
 * @license    http://gleezcms.org/license  Gleez CMS License
 */
class Datatables {

	/** Sort Ascending */
	const SORT_ASC = 'ASC';

	/** Sort Descending */
	const SORT_DESC = 'DESC';

	/**
	 * Factory pattern
	 *
	 * @param	mixed	string|object
	 * @param	mixed	NULL|string
	 * @return	Datatables
	 * @throws	Gleez_Exception
	 */
	public static function factory(ORM $object = NULL)
	{
		return new Datatables($object);
	}

	/**
	 * Whether or not current request is via DataTables
	 *
	 * @param   mixed  $request  Request [Optional]
	 * @return  boolean
	 *
	 * @uses    Request::current
	 */
	public static function is_request(Request $request = NULL)
	{
		$request = ($request) ? $request : Request::current();

		return (bool) $request->query('sEcho');
	}

	/**
	 * Object to perform paginate operations on
	 * @var object
	 */
	protected $_object;

	protected $_object_name;

	/**
	 * Columns
	 * @var array
	 */
	protected $_columns = array();

	/**
	 * Search columns
	 * @var array
	 */
	protected $_search_columns = array();

	/**
	 * Count for request
	 * @var integer
	 */
	protected $_count = 0;

	/**
	 * Total count
	 * @var integer
	 */
	protected $_count_total = 0;

	/**
	 * Result
	 * @var NULL
	 */
	protected $_result;

	/**
	 * Rows
	 * @var array
	 */
	protected $_rows = array();

	/**
	 * View
	 * @var string
	 */
	protected $_view;

	/**
	 * Request
	 * @var Request
	 */
	protected $_request;

	/**
	 * Cached render
	 * @var string
	 */
	protected $_render;

	/**
	 * Initialize
	 *
	 * @param  object  $object
	 */
	public function __construct($object)
	{
		$this->_object = $object;
		$this->_object_name = $object->object_name();
	}

	/**
	 * Apply limit
	 *
	 * @param	integer  $start   Offset
	 * @param	integer  $length  Length
	 */
	protected function _limit($start, $length)
	{
		$this->_object->offset($start)->limit($length);
	}

	/**
	 * Apply sort
	 *
	 * @param  string  $column     Column for sorting
	 * @param  string  $direction  Direction
	 */
	protected function _sort($column, $direction)
	{
		$this->_object->order_by($this->_object_name.'.'.$column, Text::plain($direction));
	}

	/**
	 * Apply search query
	 *
	 * @param  string  $query  Search query
	 */
	protected function _search($query)
	{
		// Use search columns if specified; otherwise, search across all columns
		$columns = ( ! empty($this->_search_columns)) ? $this->_search_columns : $this->_columns;


		if (count($columns) > 0)
		{
			$query = '%' . Text::plain($query) . '%';

			$this->_object->where_open();

			foreach ($columns as $key => $column)
			{
				if ($key === 0)
				{
					$this->_object->where($this->_object_name.'.'.$column, 'like', $query);
				}
				else
				{
					$this->_object->or_where($this->_object_name.'.'.$column, 'like', $query);
				}
			}

			$this->_object->where_close();
		}
	}

	/**
	 * Count
	 *
	 * @return  integer
	 */
	protected function _count()
	{
		return count($this->_result);
	}

	/**
	 * Count total
	 *
	 * @return  integer
	 */
	protected function _count_total()
	{
		return $this->_object->reset(FALSE)->count_all();
	}

	/**
	 * Execute result on object
	 *
	 * @return	mixed
	 */
	protected function _execute()
	{
		return $this->_object->find_all();
	}

	/**
	 * Set limit
	 *
	 * @param	integer  $start   Offset
	 * @param	integer  $length  Length
	 * @return	$this
	 */
	public function limit($start, $length)
	{
		$this->_limit($start, $length);

		return $this;
	}

	/**
	 * Set sort order
	 *
	 * @param	string  $column     Column for sorting
	 * @param	string  $direction  Sort order eg. SORT_ASC|SORT_DESC
	 * @return	$this
	 * @throws	Gleez_Exception
	 */
	public function sort($column, $direction = self::SORT_ASC)
	{
		if ( ! in_array($direction, array(self::SORT_ASC, self::SORT_DESC)))
		{
			throw new Gleez_Exception('Invalid sort order of `' . $direction . '`.');
		}

		$this->_sort($column, $direction);

		return $this;
	}

	/**
	 * Search query
	 *
	 * @param   string  $query  Search query
	 * @return	$this
	 */
	public function search($query)
	{
		$this->_search($query);

		return $this;
	}

	/**
	 * Get count based on post operations
	 *
	 * @return  integer
	 */
	public function count()
	{
		return (int) $this->_count;
	}

	/**
	 * Get total count prior to operations
	 *
	 * @return	integer
	 */
	public function count_total()
	{
		return (int) $this->_count_total;
	}

	/**
	 * Set or get columns
	 *
	 * @param	array  $columns  Columns for setting [Optional]
	 * @return  $this
	 */
	public function columns(array $columns = NULL)
	{
		if ($columns === NULL)
		{
			return $this->_columns;
		}

		$this->_columns = $columns;

		return $this;
	}

	/**
	 * Set or get search columns
	 *
	 * @param   array  $columns  Columns [Optional]
	 * @return  $this
	 */
	public function search_columns(array $columns = NULL)
	{
		if ($columns === NULL)
		{
			return $this->_search_columns;
		}

		$this->_search_columns = $columns;

		return $this;
	}

	/**
	 * Get result
	 *
	 * @return	mixed
	 */
	public function result()
	{
		return $this->_result;
	}

	/**
	 * Execute
	 *
	 * @return	$this
	 */
	public function execute()
	{
		$request = $this->request();

		if ( ! $request instanceof Request)
		{
			throw new Gleez_Exception('DataTables expecting valid Request. If within a sub-request, have controller pass `$this->request`.');
		}

		$columns = $this->columns();
		$this->_count_total = $this->_count_total();

		//DataTables 1.10
		if ($request->query('order') !== NULL && count($request->query('order')) )
		{
			for ($i = 0; $i < count($request->query('order')); $i++)
			{
				// Convert the column index into the column data property
				$columnIdx     = intval($request->query('order')[$i]['column']);
				$requestColumn = $request->query('columns')[$columnIdx];

				if ( $requestColumn['orderable'] == 'true' && isset($columns[$columnIdx]) ) 
				{
					$column  = $columns[$columnIdx];
					$sort    = 'Datatables::SORT_' . strtoupper($request->query('order')[$i]['dir']);

					$this->sort($column, constant($sort));
				}
			}
		}

		//DataTables 1.10
		if ($request->query('start') !== NULL && $request->query('length') != '-1')
		{
			$start  = intval($request->query('start'));
			$length = intval($request->query('length'));

			$this->limit($start, $length);
		}

		//Searching/Filtering
		if ( $request->query('search') !== NULL && $request->query('search')['value'] != '' )
		{
			$str = $request->query('search')['value'];
			for ( $i = 0, $ien = count($request->query('columns')); $i < $ien ; $i++ )
			{
				// Convert the column index into the column data property
				$requestColumn = $request->query('columns')[$i];
				//$columnIdx     = $requestColumn['data'];
				//$column        = $columns[$columnIdx];

				// global search
				if ( $requestColumn['searchable'] == 'true' )
				{
					$this->search($str);
				}
			}
		}

		//@todo - Individual column filtering
		for ( $i = 0, $ien = count($request->query('columns')); $i < $ien ; $i++ )
		{
			$requestColumn = $request->query('columns')[$i];
			//$columnIdx   = $requestColumn['data'];
			//$column      = $columns[$columnIdx];

			$str = $requestColumn['search']['value'];

			if ( $requestColumn['searchable'] == 'true'  && $str != '')
			{
				//$this->search($str);
			}
		}

		// Execute the query
		$this->_result = $this->_execute();
		$this->_count  = $this->_count();

		// Count should always match total unless search is being applied
		if ( $request->query('search') !== NULL && $request->query('search')['value'] != '' )
		{
			$this->_count = $this->count();
		}
		else
		{
			$this->_count = $this->_count_total;
		}

		return $this;
	}

	/**
	 * Set or get View file path
	 *
	 * @param	mixed	NULL|string
	 * @return	mixed	$this|string
	 */
	public function view($path = NULL)
	{
		if ($path === NULL)
		{
			return $this->_view;
		}

		$this->_view = $path;

		return $this;
	}

	/**
	 * Set or get Request
	 *
	 * @access	public
	 * @param	mixed	NULL|Request
	 * @return	mixed	$this|Request|NULL
	 */
	public function request(Request $request = NULL)
	{
		if ($request === NULL)
		{
			if ($this->_request instanceof Request)
			{
				return $this->_request;
			}

			return Request::current();
		}

		$this->_request = $request;

		return $this;
	}

	/**
	 * Add row to output
	 *
	 * @param   array  $row  Row
	 * @return  $this
	 */
	public function add_row(array $row)
	{
		$this->_rows[] = $row;

		return $this;
	}

	/**
	 * Render
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return $this->render();
	}

	/**
	 * Render
	 *
	 * @return	string
	 */
	public function render()
	{
		if ($this->_render === NULL)
		{
			if ($this->_view)
			{
				View::factory($this->_view, array('datatables' => $this))->render();
			}

			$this->request()->response()->headers('content-type', 'application/json; charset=' . Kohana::$charset);

			$this->_render = json_encode(array
			(
				'draw'              => intval($this->request()->query('draw')),
				'recordsTotal'      => intval($this->_count_total),
				'recordsFiltered'   => intval($this->_count),
				'data'              => $this->_rows
			));
		}

		return $this->_render;
	}

}