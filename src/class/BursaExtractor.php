<?php
use Propel\Runtime\Propel;
use PHPHtmlParser\Dom;

class BursaExtractor extends ExtractorBase {

	protected $page_start;
	protected $page_end;
	protected $date_from;
	protected $date_to;

	protected $current_page;

	const WEB_SERVICE_BASE_URL = 'http://ws.bursamalaysia.com/market/listed-companies/company-announcements/announcements_listing_f.html?';
	const BURSA_WEBSITE_BASE_URL = 'http://www.bursamalaysia.com';

	/**
	 *
	 * @param string $date_from		date string in DD/MM/YYYY format (with leading zero, e.g. 01, 09 etc)
	 * @param string $date_to		date string in DD/MM/YYYY format (with leading zero, e.g. 01, 09 etc)
	 * @param int	$page_start		first page no to begin process
	 * @param int	$page_end		last page no to process. If not given will be last page
	 */
	public function __construct($date_from, $date_to, $page_start=1, $page_end=null)
	{
		$this->date_from = $date_from;
		$this->date_to = $date_to;
		$this->page_start = $page_start;
		$this->page_end = $page_end;
		$this->current_page = $this->page_start;
	}

	public function build_url()
	{
		$param = array(
			'_'				=> '1386955200361',								//not sure what these two numbers stand for
			//'callback'		=> 'jQuery1620946866880171001_1386955200145',	//not sure what these two numbers stand for
			'page_category' => 'company',
			'category'		=> 'all',
			'sub_category'	=> 'all',
			'all_gm'		=> '',
			'alphabetical'	=> 'All',
			'board'			=> '',
			'sector'		=> '',
			'company'		=> '',
			'date_from'		=> $this->date_from,
			'date_to'		=> $this->date_to,
			'page'			=> $this->current_page
		);

		$final_url = sprintf('%s%s', self::WEB_SERVICE_BASE_URL, http_build_query($param));

		return $final_url;
	}

	public function process_raw_contents($raw_contents)
	{
		$rows = array();

		$proceed = false;
		do {
			$data = json_decode($raw_contents, true);
			if (!$data){
				throw new Exception('Invalid JSON in raw contents:' . json_last_error_msg());
			}

			if(!array_key_exists('html', $data) || !array_key_exists('pagination', $data)){
				throw new Exception('html or pagination element not found in json data');
			}

			$dom = new Dom();
			$dom->load($data['pagination']);
			$span = $dom->find('span.bm_total_page')[0];
			$total_page = $span->text;

			if(is_null($this->page_end)){
				$this->page_end = $total_page;
			}

			$page_rows = $this->process_single_page_html($data['html']);
			foreach($page_rows as $row){
				$rows[] = $row;
			}

			$this->current_page++;
			$proceed = $this->current_page <= $this->page_end;
			if($proceed){
				$url = $this->build_url();
				$raw_contents = $this->retrieve_raw_contents($url);
			}
		} while ($proceed);

		return json_encode($rows);
	}

	protected function process_single_page_html($html)
	{
		$rows = array();
		$dom = new Dom();
		$dom->load($html);
		$tr_list = $dom->find('table tbody tr');

		foreach($tr_list as $tr){
			$td_list = $tr->find('td');

			$row = $this->process_td_list($td_list);
			$rows[] = $row;
		}

		return $rows;

	}

	protected function process_td_list($td_list)
	{
		$row = array();
		$count = count($td_list);

		for($i=0; $i < $count; $i++){
			switch($i){
				case 0:
					break;
				case 1:
					// Date
					$row['date'] = date('Y-m-d', strtotime($td_list[$i]->text));
					break;
				case 2:
					// Company link and title
					$row['stock_code'] = null;
					$a_list = $td_list[$i]->find('a');
					// Some have no associated company and it is acceptable
					if(count($a_list) > 0){
						$href = $a_list[0]->getAttribute('href');
						if(!$href){
							throw new Exception('No href in company td a');
						}
						list($not_used, $stock_code) = explode('stock_code=', $href);
						$row['stock_code'] = $stock_code;
					}
					//echo $row['stock_code'] . "\n";
					break;
				case 3:
					// Announcement link and title
					$a_list = $td_list[$i]->find('a');
					if(!$a_list){
						throw new Exception('No announcement link in announcement title td');
					}
					$href = $a_list[0]->getAttribute('href');
					if(!$href){
						throw new Exception('No href in announcement title td a');
					}

					$row['title'] = $a_list[0]->text;
					$row['announcement_url'] = sprintf('%s%s', self::BURSA_WEBSITE_BASE_URL, $href);

					break;
				default:
					break;
			}
		}

		$row['hash'] = md5($row['stock_code'] . $row['url']);

		return $row;
	}
}

