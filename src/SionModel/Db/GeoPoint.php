<?php
namespace SionModel\Db;

class GeoPoint
{
    public $longitude = 0;
    public $latitude = 0;

    public function __construct($longitude, $latitude)
    {
        $this->longitude = $longitude;
        $this->latitude = $latitude;
    }

    public function __toString()
    {
        return (is_numeric($this->latitude) ? $this->latitude: '0').
        ','.(is_numeric($this->longitude) ? $this->longitude: '0');
    }

    public function getDatabaseInsertString()
    {
        return 'POINT('.(is_numeric($this->longitude) ? $this->longitude : '0').
        ','.(is_numeric($this->latitude) ? $this->latitude: '0').')';
    }
}