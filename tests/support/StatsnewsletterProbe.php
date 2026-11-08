<?php

class StatsnewsletterProbe extends statsnewsletter
{
    public function invokeGetData($layers)
    {
        $this->_values = array();
        $this->_legend = array();
        $this->getData($layers);
    }

    public function getValuesSnapshot()
    {
        return $this->_values;
    }

    public function getTitlesSnapshot()
    {
        return $this->_titles;
    }
}
