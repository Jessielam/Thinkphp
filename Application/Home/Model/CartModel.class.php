<?php
namespace Home\Model;
use Think\Model;

class CartModel extends Model{
	protected $insertFields = 'goods_id, goods_attr_id, goods_number';
	protected $_validate = array(
		array('goods_id','require','必须选择商品'),
		array('goods_number', 'chkGoodsNumber', '库存量不足!', 1, 'callback'),
	);

	public function chkGoodsNumber($goodsNumer){
		$gaId = I('post.goods_attr_id');
		//对数组进行数字升序排序
		sort($gaId, SORT_NUMERIC);
		$gaId = implode(',', $gaId);
		$gnModel = D('goods_number');
		$gn = $gnModel->field('goods_number')->where(array(
				'goods_id'	=> I('post.goods_id'),
				'goods_attr_id'	=> $gaId
			))->find();

		return ($gn['goods_number']>=$goodsNumber);
	}

	//重写父类的add方法，先进行判断用户是否已经登录决定数据保存在哪里
	public function add(){
		$memberId = session('m_id');
		$postData = I('post.');
		$this->goods_number = $postData['goods_number'];
		$goods_attr_id = $postData['goods_attr_id'];
		
		if(isset($goods_attr_id)){
			sort($goods_attr_id, SORT_NUMERIC);
			$this->goods_attr_id = implode(',', $goods_attr_id);
		}else{
			$this->goods_attr_id = "";
		}
		
		if($memberId){
			$has = $this->field()->where(array(
				'member_id' => $memberId,
				'goods_id'	=> $this->goods_id,
				'goods_attr_id'	=> $this->goods_attr_id,
			))->find();
			//如果购物车中已经有了这件商品，这件商品的数量加上数量
			if($has){
				$this->where(array(
					'id' => array('eq',$has['id']),
				))->setInc('goods_number', $this->goods_number);
			}else{
				parent::add(array(
					'member_id' => $memberId,
					'goods_id'	=> $this->goods_id,
					'goods_attr_id'	=> $this->goods_attr_id,
					'goods_number'	=> $this->goods_number
				));

				//echo $this->getLastSql();die;
			}
		}else{
			//从cookie取出购物车的一维数组，没有则返回空数组
			$cart = isset($_COOKIE['cart'])?unserialize($_COOKIE['cart']):array();
			$key = $this->goods_id."-".$this->goods_attr_id;
			if(isset($cart[$key])){
				$cart[$key] += $this->goods_number;
			}else{
				$cart[$key] = $this->goods_number;
			}
			//把一维数组存回COOKIE数组中
			setcookie('cart', serialize($cart), time()+30*86400, '/');
		}

		return TRUE;
	}

	//用户登录后，把购物车的数组从COOKIE中移到数据库中
	public function moveDataToDb(){
		$memberId = session('m_id');

		if($memberId){
			//获取cookie中的数据
			$cart = isset($_COOKIE['cart'])?unserialize($_COOKIE['cart']):array();
			//循环cookie中的数据
			foreach($cart as $k=>$v){
				$_k = explode('-', $k);

				//查询购物车中是否已经存在该件产品的记录
				$has = $this->field('id')->where(array(
						'member_id' => $memberId,
						'goods_id'	=> $_k[0],
						'goods_attr_id'	=> $_k[1],
					))->find();

				if($has){
					$this->where(array(
						'id' => array('eq',$has['id']),
					))->setInc('goods_number', $v);
				}else{
					parent::add(array(
						'member_id' => $memberId,
						'goods_id'	=> $_k[0],
						'goods_attr_id'	=> $_k[1],
						'goods_number'	=> $v
					));
				}
			}
		}
		//把数组移近数据库之后，把cookie清空
		setcookie('cart', '', time()-1, '/');
	}

	/**
	 * 获取购物购物车中商品的详细信息
	 * @author Homelam
	 * @return Array
	 */
	public function cartList(){
		/***********先购物车中取出商品的id*****/
		$memberId = session('m_id');
		if($memberId){
			$data = $this->where(array(
				'member_id' => array('eq', $memberId),
			))->select();
		}else{
			$_data = isset($_COOKIE['cart'])? unserialize($_COOKIE['cart']):array();
			//把以为数组转化成和上面一样的二维数组
			$data = array();
			foreach($_data as $k=>$v){
				$_k = explode('-', $k);
				$data[] = array(
					'goods_id' => $_k[0],
					'goods_attr_id' => $_k[1],
					'goods_number'	=> $v,
				);
			}
		}

		//在根据商品id取出商品的详细信息
		$gModel = D('Admin/Goods');
		$gaModel = D('goods_attr');
		foreach($data as $k=>&$v){
			//取出商品名字和logo
			$info = $gModel->field('goods_name, mid_logo')->find($v['goods_id']);
			//在存回这个二维数组
			$v['goods_name'] = $info['goods_name'];
			$v['mid_logo'] = $info['mid_logo'];
			//计算实际购买价格
			$v['price'] = $gModel->getMemberPrice($v['goods_id']);

			//根据商品属性id计算出商品属性名称和属性值： 属性名称：属性值
			if($v['goods_attr_id']){
				$v['gaData'] = $gaModel->alias("a")
					->field("a.attr_value,b.attr_name")
					->join("__ATTRIBUTE__ b ON a.attr_id=b.id")
					->where(array(
						'a.id'	=> array('IN', $v['goods_attr_id']),
					))->select();
			}	
		}

		return $data;
	}
}