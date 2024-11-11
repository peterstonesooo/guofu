<?php
namespace app\common;

use think\paginator\driver\Bootstrap;


class CustomBootstrap extends Bootstrap
{
        /**
     * 渲染分页html
     * @return mixed
     */
    public function render()
    {
        $currentUrl = $this->url($this->currentPage());
        $perPageSelect = '';
        if($this->options['path']=="/admin/Capital/withdrawList.html"){
            $perPageOptions = [10, 20, 30, 50, 100];
            $perPageSelect = '<li><select class="form-control" style="display:inline;width:100px" onchange="location.href=this.value;">';
            foreach ($perPageOptions as $option) {
                $url = $currentUrl . '&per_page=' . $option;
                $perPageSelect .= '<option value="' . $url . '"  url="'.$url.'">' . $option . '</option>';
            }
            $perPageSelect .= '</select></li>';
        }
        if ($this->hasPages()) {

            if ($this->simple) {
                return sprintf(
                    '<ul class="pager">%s %s</ul>',
                    $this->getPreviousButton(),
                    $this->getNextButton()
                );
            } else {
                return sprintf(
                    '<ul class="pagination">%s %s %s %s %s</ul>',
                    $this->getPreviousButton(),
                    $this->getLinks(),
                    $this->getNextButton(),
                    "<li><span>共".$this->total()."条 ".$this->listRows()."/页</span><li> <li>",
                    //增加自定义每页条数的下拉框10,20,30,50,100
                    $perPageSelect

                );
            }
        }else{
            return sprintf(
                '<ul class="pagination">%s %s</ul>',
                "<li><span>共".$this->total()."条 ".$this->listRows()."/页</span><li> <li>",
                //增加自定义每页条数的下拉框10,20,30,50,100
                $perPageSelect

            );
        }
    }   
}