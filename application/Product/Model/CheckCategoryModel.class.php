<?php

namespace Product\Model;
use Think\Model;

class CheckCategoryModel extends Model {

    //停用代码
    public function get_option_tree(){
        $cat = $this->select();
        $tree = new \Tree();
        $tree->icon = array('│ ', '├─ ', '└─ ');
        $tree->nbsp = '&nbsp;';

        $category = array();
        foreach ($cat as $row) {
            $category[$row['id_category']] = array(
                'id' => $row['id_category'],
                'parentid' => $row['parent_id'],
                'name' => $row['title']
            );
        }

        $tree->init($category);
        $str = "<option value='\$id'>\$spacer\$name</option>";
        $selected= explode(',', $_GET['category']);
        $selected = empty($selected) ? 0 : $selected;
        return $tree->get_tree(0, $str, 6);
    }

    //获取产品分类数据
    public function getCategory ()
    {
        $cat = $this->select();
        $category = array();
        foreach ($cat as $row) {
            $category[] = array(
                'id' => intval($row['id_category']),
                'pId' => intval($row['parent_id']),
                'name' => $row['title']
            );
        }
        return $category;

        //递归获取zTree数组格式数据(调试)
        function findChild(&$arr,$id)
        {
            $children = array();
            foreach ($arr as $k => $v)
            {
                if ($v['pId']== $id)
                {
                    $children[]=$v;
                }
            }
            return $children;
        }

        function build_tree($rows,$root_id)
        {
            $children = findChild($rows,$root_id);
            if (empty($children))
            {
                return null;
            }
            foreach ($children as $k => $v)
            {
                $zTree = build_tree($rows,$v['id']);
                if( null != $zTree)
                {
                    $children[$k]['children'] = $zTree;
                }
            }
            return $children;
        }
        return $tree=build_tree($category,0);

    }

}

