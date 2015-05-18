<?php
/*
*模板引擎
*/
Class Sundially{
	private $arrayConfig=array(
		'suffix'=>'.m',//设置模板文件的后缀
		'templateDir'=>'template/',//设置模板的所在的文件夹
		'compileDir'=>'compile/',//设置编译后存放的目录
		'cache_htm'=>false,//是否需要编译生成静态的html文件
		'suffix_cache'=>'.htm',//设置编译文件的后缀名
		'cache_time'=>10,//多长时间自动更新，单位秒
		'php_turn'=>true,//是否支持php原生代码
		'cache_control'=>'control.dat',
		'debug'=>false
		);
	public $file;//模板文件名,不带路径
	private $value=array();//值栈
	private $compileTool;//编译器
	static private $instance=null;
	public $debug=array();//调试信息
	private $controlData=array();

	public function __construct($arrayConfig=array()){
		$this->debug['begin']=microtime(true);
		$this->arrayConfig=$arrayConfig+$this->arrayConfig;
		$this->getPath();

		if(!is_dir($this->arrayConfig['templateDir'])){
			exit('template dir is not found');
		}
		if(!is_dir($this->arrayConfig['compileDir'])){
			mkdir($this->arrayConfig['compileDir'],0770,true);
		}
		include ('CompileClass.php');
	}
	/*
	*路径为绝对处理路径
	*这里主要是对windows的路径进行替换
	*realpath()完整路径
	*/
	public function getPath(){
		$this->arrayConfig['templateDir']=strtr(realpath($this->arrayConfig['templateDir']), '\\', '/').'/';
		$this->arrayConfig['compileDir']=strtr(realpath($this->arrayConfig['compileDir']), '\\', '/').'/';
	}
	/**
	*取得模板引擎的实例
	*return object
	*access public
	*static
	*/
	public static function getInstance(){
		if(is_null(self::$instance)){
			self::$instance=new Sundially();
		}
		return self::$instance;
	}
	/*单步引擎设置*/
	public function setConfig($key,$value=null){
		if(is_array($key)){
			$this->arrayConfig=$key+$this->arrayConfig;
		}else{
			$this->arrayConfig[$key]=$value;
		}
	}
	/*获取当前模板引擎配置，仅供测试使用*/
	public function getConfig($key=null){
		if($key){
			return $this->arrayConfig[$key];
		}else{
			return $this->arrayConfig;
		}
	}
	/*向模板文件注入变量*/
	/**
	*注入单个变量
	*@param string $key模板变量名
	*@param mixed $value模板变量value
	*@return void
	*/
	public function assign($key,$value){
		$this->value[$key]=$value;
	}
	/**
	*注入数组变量
	*@param array $array
	*/
	public function assignArray($array){
		if(is_array($array)){
			foreach ($array as $k => $v) {
				$this->value[$k]=$v;
			}
		}
	}
	//path() function   muban
	public function path(){
		return $this->arrayConfig['templateDir'].$this->file.$this->arrayConfig['suffix'];
	}
	/**
	*判断是否开启了缓存
	*/
	public function needCache(){
		return $this->arrayConfig['cache_htm'];
	}
	/**
	*判断是否需要重新生成静态文件
	*@param $file
	*@return bool
	*/
	public function reCache($file){
		$flag=false;
		$cacheFile=$this->arrayConfig['compileDir'].md5($file).'.htm';
		if($this->arrayConfig['cache_htm']===true){//是否需要缓存
			$timeFlag=(time()-filemtime($cacheFile))<$this->arrayConfig['cache_time']?true:false;
			if(is_file($cacheFile)&&filesize($cacheFile)>1&&$timeFlag){//缓存存在未过期
				$flag=true;
			}else{
				$flag=false;
			}
		}
		return $flag;
	}
	//show function
	public function show($file){
		$this->file=$file;
		if(!is_file($this->path())){
			exit('找不到对应模板');
		}
		$compileFile=$this->arrayConfig['compileDir'].md5($file).'.php';
		$cacheFile=$this->arrayConfig['compileDir'].md5($file).'.htm';
		if($this->reCache($file)===false){
			$this->debug['cached']='false';
			$this->compileTool=new CompileClass($this->path(),$compileFile,$this->arrayConfig);
			if($this->needCache()){
				ob_start();
			}
			//zhuyi extract()
			extract($this->value,EXTR_OVERWRITE);
			if(!is_file($compileFile)||filemtime($compileFile)<filemtime($this->path())){
				$this->compileTool->vars=$this->value;
				$this->compileTool->compile();
				include $compileFile;
			}else{
				include $compileFile;
			}
			if($this->needCache()){
				$message=ob_get_contents();
				file_put_contents($cacheFile, $message);
			}
		}else{
			readfile($cacheFile);
			$this->debug['cached']='true';
		}
		$this->debug['spend']=microtime(true)-$this->debug['begin'];
		$this->debug['count']=count($this->value);
		$this->debug_info();
	}


	//debug_info()
	public function debug_info(){
		if($this->arrayConfig['debug']===true){
			echo PHP_EOL,'---------debug info--------',PHP_EOL;
			echo '程序运行日期:',date('Y-m-d H:i:s'),PHP_EOL;
			echo '模板解析耗时:',$this->debug['spend'],'秒',PHP_EOL;
			echo '模板包含标签数目:',$this->debug['count'],PHP_EOL;
			echo '是否使用静态缓存:',$this->debug['cached'],PHP_EOL;
			echo '模板引擎实例参数:',var_dump($this->getConfig());
		}
	}
	/*
	*清理缓存的html文件
	*@param null $path
	*/
	public function clean($path=null){
		if($path==null){
			$path=$this->arrayConfig['compileDir'];
			$path=glob($path.'* '.$this->arrayConfig['suffix_cache']);
		}else{
			$path=$this->arrayConfig['compileDir'].md5($path).'.htm';
		}
		foreach ((array) $path as $v) {
			unlink($v);
		}
	}
}
