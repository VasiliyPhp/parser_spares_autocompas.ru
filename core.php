<?php
	
	// поиск всех марок авто
	function get_main_categories(){
		$cats = get('cats');
		if(!$cats){
			$tmp = file_get_contents(SITE);
			$document = phpQuery::newDocument($tmp);
			$i = 0;
			foreach(pq('.classes-list a') as $item){
				
				$cats[$i]['href']  = pq($item)->attr('href');
				$cats[$i]['title'] = pq($item)->text();
				$i++;
			}
			set('cats', $cats);
		}
		phpQuery::unloadDocuments();
		j($cats);
		return ($cats) ? : [];
	}
	
	// поиск моделей и подмоделей
	function parse($cat){
		
		// поиск моделей
		$submodelsDoc = null;
		$models = file_get_contents(SITE . '/zapchasti/' . $cat);
		
		$doc = phpQuery::newDocument($models);
		
		$models = [];
		
		foreach(pq('.classes-list a') as $i=>$item){
			
			$models[$i]  = [
			'href'=>pq($item)->attr('href'),
			'marka'=>pq('.bx-breadcrumb-item:last span')->text(),
			'model'=>pq($item)->text(),
			];
		}
		$doc->unloadDocument();
		
		$mcurl = new Curl\MultiCurl;
		$mcurl->setConcurrency(4);
		$mcurl->setOpts([CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);
		$mcurl->setConnectTimeout(2);
		
		$mcurl->complete(function($instance) use ($mcurl){
			curl_close($instance->curl);
			curl_multi_remove_handle($mcurl->multiCurl, $instance->curl);
			gc_collect_cycles();
		});
		
		// поиск подмоделей
		$submodel = [];
		$mcurl->success(function($instance) use (&$submodel){
			check();
			$doc = $instance->response;
			$submodelsDoc = phpQuery::newDocument($doc)->find('.generations-item a');
			$model = $instance->model;
			$marka = $instance->marka;
			foreach($submodelsDoc as $subm){
				$_submodel = trim(pq($subm)->attr('title'));
				$href = trim(pq($subm)->attr('href'));
				if(exists($model,$_submodel)){
					s("Пропускаем $marka $_submodel");
					continue;
				}
				$submodel[] = [
				'marka'=>$marka,
				'model'=>$model,
				'submodel'=>$_submodel,
				'href'=>SITE . $href,
				];
			}
			$submodelsDoc->unloadDocument();
		});
		
		foreach($models as $item){
			$curl = $mcurl->addGet(SITE . $item['href']);
			$curl->model = $item['model'];
			$curl->marka= $item['marka'];
		}
		$mcurl->start();
		$mcurl->close();
		// нашли подмодели , осталось найти их вариации двигателей
		
		unset($mcurl);
		gc_collect_cycles();
		
		$mcurl = new Curl\MultiCurl;
		$mcurl->setConcurrency(4);
		$mcurl->setConnectTimeout(2);
		$mcurl->setOpts([CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);
		
		// подмодели с разделением на двигатели
		$submodels = [];
		
		$mcurl->success(function($instance) use (&$submodels){
			check();
			$data = $instance->submodel;
			s(sprintf("собрали двигатель %s %s %s", $data['marka'], $data['model'], $data['submodel']));
			$doc = phpQuery::newDocument($instance->response);
			foreach(pq('.cat2.types li a') as $item){
				$tmp = $data;
				$tmp['href'] = pq($item)->attr('href');
				$tmp['dvs'] = pq($item)->text();
				$submodels[] = $tmp;
			}
			$doc->unloadDocument();
		});
		
		$mcurl->complete(function($instance) use ($mcurl){
			curl_close($instance->curl);
			curl_multi_remove_handle($mcurl->multiCurl, $instance->curl);
			gc_collect_cycles();
		});
		foreach($submodel as $item){
			$curl = $mcurl->addGet($item['href']);
			$curl->submodel = $item;
		}
		$mcurl->start();
		$mcurl->close();
		unset($mcurl);
		gc_collect_cycles();
		
		return get('cats_' . $submodels[0]['marka']) ? : set('cats_' . $submodels[0]['marka'], find_cats($submodels));
		
	}
	
	function get_oil($href){
		
		$doc = phpQuery::newDocumentFile(($href));
		
		$html = preg_replace('~[\t\r\n]~', '', pq('.item-details')->html());
		
		$doc->unloadDocument();
		
		return $html;
		
	}
	
	function get_lights($href){
		
		$doc = phpQuery::newDocumentFile(($href));
		
		$html = preg_replace('~[\t\r\n]~', '', str_replace('src="/auimg/', 'src="/upload/auimg/', pq('.item-details')->html()));
		
		$doc->unloadDocument();
		
		return $html;
		
	}
	
	function get_analogi($tmp){
		$curl = new \Curl\Curl;
		$curl->setOpts([CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);
		$curl->setHeader('X-Requested-With', 'XMLHttpRequest');
		$curl->post('https://www.autocompas.ru/include/api/loading_prices2.php', array(
		'art_id'=>$tmp[1],
		'art_code'=>$tmp[2],
		'art_article'=>$tmp[4],
		'art_supp'=>$tmp[5],
		)
		);
		
		if ($curl->error) {
			echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
		}
		preg_match('~prices_cross_content\s*=\s*"(.+)"~',$curl->response, $response);
		$curl->close();
		unset($curl);
		$doc = json_decode("\"".$response[1]."\"");
		$doc = phpQuery::newDocument($doc);
		$analogi = pq('.parts-item');
		$analogs = [];
		foreach($analogi as $key=>$analog){
			pq('.parts-item-article', $analog)->find(':child')->remove();
			pq('.parts-item-brand', $analog)->find(':child')->remove();
			$article = trim(pq('.parts-item-article', $analog)->text());
			$brand = trim(pq('.parts-item-brand', $analog)->text());
			$id = get_analog($article, $brand);
			if(!$id){ 
				$id = get_spare($article, $brand);
				if($id){
					$id = $id['id'];
				}
				else {
					$id = save_analog($article, $brand);
				}
			}
			$analogs[] = $id;
			// $analogs[$key]['href'] = SITE . pq('.parts-item-title a', $analog)->attr('href');
		}
		$doc->unloadDocument();
		return $analogs;
	}
	
	function get_path($pref, $str){
		$path = $pref . '\\' . str_replace('-', '\\', $str);
		return $path;
	}
	
	function get_analog($article, $brand){
		$brand = translit($brand);
		$article = translit($article);
		$path = get_path('analogs', $brand. '-' . mb_substr($article,0,1) . '-' . mb_substr($article, 1, 1) );
		$file = $path . '/' . $article;
		if(file_exists($file)){
			return file_get_contents($file);
		}
		return null;
		
	}
	
	function get_spare($article, $brand){
		$brand = translit($brand);
		$article = translit($article);
		$path = get_path('spares', $brand. '-' . mb_substr($article,0,1) . '-' . mb_substr($article, 1, 1) );
		$file = $path . '/' . $article;
		if(file_exists($file)){
			return unserialize(file_get_contents($file));
		}
		return null;
	}
	
	function save_analog($article, $brand){
		$brand = translit($brand);
		$article = translit($article);
		$path = get_path('analogs', $brand. '-' . mb_substr($article,0,1) . '-' . mb_substr($article, 1, 1) );
		file_exists($path) or mkdir($path, 0777, 1);
		$file = $path . '/' . $article;
		$id = id();
		// s("сохнаняем аналог $file",1);
		file_put_contents($file, $id);
		return $id;
	}
	
	function save_spare($article, $brand, $data){
		$brand = translit($brand);
		$article = translit($article);
		$path = get_path('spares', $brand. '-' . mb_substr($article,0,1) . '-' . mb_substr($article, 1, 1) );
		file_exists($path) or mkdir($path, 0777, 1);
		$file = $path . '/' . $article;
		// $id = id();
		// $data['id'] = $id;
		file_put_contents($file, serialize($data));
		// return $id;
	}
	
	function find_cats($submodels){
		
		// теперь собюираем все категории с подкатегориями...
		$mcurl = new Curl\MultiCurl;
		$mcurl->setConcurrency(4);
		$mcurl->setConnectTimeout(2);
		$mcurl->setOpts([CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);
		$mcurl->complete(function($instance) use ($mcurl){
			check();
			gc_collect_cycles();
			curl_close($instance->curl);
			curl_multi_remove_handle($mcurl->multiCurl, $instance->curl);
		});
		
		$mcurl->success(function ($instance){
			$catsList = [];
			$data = $instance->submodels;
			$subcatsDoc = phpQuery::newDocument($instance->response);
			
			$car_params = [];
			foreach(pq('.type-info-item') as $param){
				// j(pq($param)->text()); die; 
				list($key, $value) = array_map('trim', explode(':', pq($param)->text()));
				$car_params[$key] = $value;
			}
			$oil = get_oil( SITE . pq('.technicals-lbl a:contains(Подбор масла и жидкостей)')->attr('href') );
			
			$lights = get_lights( SITE . pq('.technicals-lbl a:contains(Маркировка автоламп)', $subcatsDoc)->attr('href') );
			$list = pq('#qgTree>ul>li', $subcatsDoc);
			foreach($list as $item){
				$subcats = pq('>.qgContainer>li .qgContent a', $item);
				$category = trim(pq('>div.qgContent',$item)->text());
				s(sprintf("%s %s (%s) - %s", $data['marka'], $data['submodel'], $data['dvs'], $category));
				foreach($subcats as $subcat){
					$tmp = $data;
					$tmp['href'] = SITE . pq($subcat)->attr('href');
					$tmp['cat'] = $category;
					$tmp['subcat'] = pq($subcat)->text();
					$tmp['car_params'] = $car_params;
					$tmp['oil'] = '[html]' . $oil;
					$tmp['lights'] = '[html]' . $lights;
					$catsList[] = $tmp;
				}
			}
			$subcatsDoc->unloadDocument();
			foreach($catsList as $key=>&$item){
				$links = find_links($item);
				$item = null;
				unset($item);
				s(sprintf('поиск %s категории из %s - %s %s %s', (1+$key), count($catsList), $data['marka'], $data['submodel'], $data['dvs']));
				count($links) && find_spares($links);
			}
		});
		
		foreach($submodels as $item){			
			$curl = $mcurl->addGet(SITE . $item['href']);			
			$curl->submodels = $item;
		}
		
		$mcurl->start();
		$mcurl->close();
		gc_collect_cycles();
		unset($mcurl);
	}
	
	function find_links($item, $href = null){
		check();
		$links = [];
		$href = $href ? : $item['href'];
		// s('страница ' . $href, 1);
		$doc = @file_get_contents($href);
		$referer = $href;
		if(!$doc){
			return [];
		}
		$doc = phpQuery::newDocument($doc); 
		$list = pq('.bx-item-content');
		foreach($list as $element){
			
			$href = SITE . pq('.bx-item-name-link', $element)->attr('href');
			$title  = trim(pq('.bx-item-name-link', $element)->text());
			$sku = pq('.bx-item-prop:contains(Артикул) .bx-item-prop-value:eq(0)',$element)->text();
			if(!$sku){
				s(sprintf(' нет артикула %s', $href),1);
				continue;
			}
			$manufacturer = pq('.bx-item-prop:contains(Производитель) .bx-item-prop-value',$element);
			$country = pq($manufacturer)->find('.supplier-country')->text();
			if($_man = pq($manufacturer)->find('a.tip')->text()){
				$manufacturer = $_man;
			}
			else{
				pq($manufacturer)->find(':child')->remove();
				$manufacturer = $manufacturer->text();
			}
			$tmp = $item;
			$tmp['country'] = $country;
			$tmp['manufacturer'] = $manufacturer;
			$tmp['href'] = $href;
			$tmp['sku'] = $sku;
			$_spare = get_spare($sku, $manufacturer);
			if($_spare){
				if($_spare['referer'] == $referer){
					s('Повтор ' . $sku . ' ' . $manufacturer . ' ' . $referer);
				}
				else{
					s('Дублируется запчасть в другой категории ' . $sku . ' ' . $manufacturer . ' ' . $referer . ' ' . $_spare['referer']);
					$tmp = array_replace_recursive($_spare, $tmp);
					// j($tmp);
					write_spare($tmp);
					// $links[] = $tmp;
				}
			}
			else{
				$tmp['title'] = $title . ' ' . $tmp['sku'];
				$tmp['referer'] = $referer;
				$links[] = $tmp;
			}
			// j($links);
			$pagi = pq('.bx_pagination_page>ul>li>a:last');
			if($pagi->attr('title') == 'Следующая страница'){
				$doc->unloadDocument();
				$merged = find_links($item, SITE . $pagi->attr('href'));
				$links = array_merge($links, $merged);
			}
			else{
				// s('нет пагинации');
				$doc->unloadDocument();
			}
			return $links;
		}
	}
	
	function find_spares($items){
		check();
		$mcurl = new Curl\MultiCurl;
		$mcurl->setConcurrency(6);
		$mcurl->setConnectTimeout(2);
		$mcurl->setOpts([CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);
		foreach($items as $item){
			$curl = $mcurl->addGet($item['href']);
			$curl->catalog = $item;
		}
		$mcurl->error(function($instance) use (&$links, $mcurl){
			// global $new_links;
			// if(isset($links[$instance->url]['try'])){
			// if($links[$instance->url]['try'] < 50 ){
			// ++$links[$instance->url]['try'];
			// $new_links[$instance->url] = $links[$instance->url];
			// }
			// } else {
			// $links[$instance->url]['try'] = 0;
			// $new_links[$instance->url] = $links[$instance->url];
			// }
			// s('Ошибка - ' . $instance->url . ' ' . $links[$instance->url]['try'],2);
		});
		$mcurl->complete(function($instance) use ($mcurl){
			curl_multi_remove_handle($mcurl->multiCurl, $instance->curl);
			curl_close($instance->curl);
			gc_collect_cycles();
		});
		$mcurl->success(function($instance){
			check();
			$doc = phpQuery::newDocument($instance->response);
			// $doc = phpQuery::newDocument(file_get_contents('https://www.autocompas.ru/detail/valeo-032103-lampa-nakalivaniya-fonar-ukazatelya-povorota/'));
			$catalog = $instance->catalog;
			$params= pq('.details-parameters ul li');
			$meta = [];
			foreach($params as $param){
				$meta[] = pq($param)->text();
			}
			$spare = $catalog;
			$spare['meta'] = implode('; ', $meta);
			
			$images = pq('.gallery a');
			$imgs = [];
			foreach($images as $image){
				$imgs[] = SITE . pq($image)->attr('href');
			}
			if($images->length == 0){
				$imgs = [''];
			}
			foreach($meta as $car_param){
				list($key, $value) = array_map('trim', explode(':', $car_param));
				$spare[$key] = $value;
			}
			$tmp = [];
			preg_match('~var art_id = "([^"]+)";\s+var art_code = "([^"]+)";\s+var art_name = "([^"]+)";\s+var art_article = "([^"]+)";\s+var art_supp = "([^"]+)";~iUs', $instance->response, $tmp);
			$instance->response = null;
			if(!isset($spare['images'])){
				$spare['images'] = $imgs;
				$spare['images'] = save_spares_images($spare);
			}
			// $spare['oil'] = '"[html]' . $catalog['oil'] . '"';
			// $spare['lights'] = '"[html]' . $catalog['lights'] . '"';
			$spare['oil'] = $catalog['oil'];
			$spare['lights'] = $catalog['lights'];
			$spare['analogi'] = implode('|', get_analogi($tmp));
			$spare['referer'] = $catalog['referer'];
			
			$spare['id'] = get_analog($catalog['sku'], $catalog['manufacturer']) ? : id();
			$spare['id2'] = $spare['id'];
			$spare['title2'] = $spare['title'];
			$spare['IE_ACTIVE'] = 'Y';
			$spare['analog'] = 'Y';
			$spare['original'] = 'N';
			$spare['IE_CODE'] = translit($spare['title']) /* . '_' . substr(md5($spare['referer']), 0, 2) */;
			save_spare($catalog['sku'], $catalog['manufacturer'], $spare);
			// j($spare);
			
			write_spare($spare);
			
			$doc->unloadDocument();
			gc_collect_cycles();
		}); 
		$mcurl->start();
		$mcurl->close();
		unset($mcurl);
		phpQuery::unloadDocuments();
	}
	function save_spares_images($spare){
		$marka = translit($spare['marka']);
		$model = translit($spare['model']);
		$submodel = translit($spare['submodel']);
		$path = "_catalog/$marka/imgs/$submodel/";
		file_exists($path) || mkdir($path, 0777, 1);
		$images = [];
		foreach($spare['images'] as $img){
			if(!$img){
				continue;
			}
			$ext = explode('.', $img);
			$ext = end($ext);
			$img_path = $path . md5($img) . '.' . $ext;
			// s("Сохраняем картинку $img",1);
			file_exists($img_path) || file_put_contents($img_path, file_get_contents($img));
			$images[] = str_replace('_catalog', '/upload', $img_path);
		}
		return implode('|', $images);
	}
	function exists(){
		list($file, $path) = call_user_func_array('make_path', func_get_args());
		return file_exists($file);
	}
	function save(){
		list($file, $path) = call_user_func_array('make_path', func_get_args());
		file_exists($path) || mkdir($path, null, 1);
		touch($file);
	}
	
	function make_path(){
		$args = array_map(function($item){
			return iconv('utf-8','cp1251',translit($item));
		} , func_get_args());
		
		$path = count($args) > 1 ? 'check_dir/' . implode('/' , array_slice($args, 1)) . '/' : 'check_dir/';
		
		$file = $path . $args[0]; 
		
		return [$file, $path];
	}
	
	function write_spare($ar){
		
		foreach($ar['car_params'] as $prop=>$car_param){
			$ar[$prop] = $car_param;
		}
		unset($ar['car_params']);
		extract(array_map('trim',$ar));
		$props = require 'prop_val.php';
		$csv_path = "_catalog/$marka/csv/";
		file_exists($csv_path) || mkdir($csv_path,null,1);
		$csv_name = $csv_path . translit($marka) . '.csv';
		if(!file_exists($csv_name)){
			$header = array_keys($props);
			$fd = fopen($csv_name, 'a');
			fputcsv($fd,$header,';');
		}
		else{
			$fd = fopen($csv_name, 'a');
		}
		$data = [];
		foreach($props as $prop=>$val){
			$data[$prop] = isset($ar[$val]) ? $ar[$val] : '';
		}
		// $data = array_map(function($i){
		// $val = iconv('utf-8','cp1251',$i);
		// if($error = error_get_last()){
		// if($error['file'] == 'C:\htdocs\parser_spares2\vendor\coderockr\php-query\src\phpQuery\DOMDocumentWrapper.php'){ continue;}
		// s($val . '  -  ' . $i, 2);
		// s(print_r($error,1));
		// set_error_handler('var_dump', 0); 
		// restore_error_handler(); 
		// }
		// return $val;
		// },$data);
		foreach($data as $key=>&$value){
			$value = html_entity_decode(iconv('utf-8','cp1251',htmlentities($value)));
		}
		// j($data);
		fputcsv($fd,$data,';');
		fclose($fd);
		s('сохранили ' . $ar['title']);
		return null;
		return $id;
	}
	function id($id = null){
		if($id){
			file_put_contents('iddata', $id);
			return $id;
		}
		
		if(file_exists('iddata')){
			$id = file_get_contents('iddata');
			
			if(!$id){
				sleep(2);
				$id = file_get_contents('iddata');
			}
		}
		else {
			$id = 1;
		}
		
		$id = (int)$id;
		
		if( $id < 1 ) {
			s('Не удалось прочитать id');
			exit();
			s($id .'<' . 235) ;
			$id = 235;
		}
		$id++;
		// s($id);
		file_put_contents('iddata',$id);
		file_put_contents('iddata2',$id);
		return $id;
	}
	function translit($s) {
		$s = (string) $s; // преобразуем в строковое значение
		$s = strip_tags($s); // убираем HTML-теги
		$s = trim($s); // убираем пробелы в начале и конце строки
		$s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
		$s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда надо задать локаль)
		$s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
		$s = preg_replace("/[^A-z\d]/i", "_", $s); // заменяем все двойные подчеркивания на одно
		$s = preg_replace("/_+/i", "_", $s); // заменяем все двойные подчеркивания на одно
		// $s = preg_replace("/^_(.*)_$/i", "$1", $s); // заменяем подчеркивания в конце и в начале слова на ''
		return $s; // возвращаем результат
	}			