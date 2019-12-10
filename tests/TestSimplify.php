<?php


use phpRdp\phpRdp;

class TestSimplify extends \PHPUnit\Framework\TestCase
{

    public function testRamerDouglasPeucker_simplify()
    {
        $track = json_decode('[{"point":{"lat":59.936395,"lon":30.317944},"id":1},
                                          {"point":{"lat":59.937471,"lon":30.322987},"id":2},
                                          {"point":{"lat":59.934480,"lon":30.333330},"id":3},
                                          {"point":{"lat":59.933037,"lon":30.347964},"id":4},
                                          {"point":{"lat":59.930941,"lon":30.361854},"id":5}]', true);
        $this->assertSame(5, count($track));

        // test init without epsilon
        $rdp = new phpRdp('point.lat', 'point.lon');
        $this->assertSame(5, count($rdp->RamerDouglasPeucker($track)));

        // test init with epsilon=0.001
        $rdp = new phpRdp('point.lat', 'point.lon', 0.001);
        $this->assertSame(5, count($rdp->RamerDouglasPeucker($track)));

        // test init with epsilon=0.001
        $rdp = new phpRdp('point.lat', 'point.lon', 0.01);
        $this->assertSame(5, count($rdp->RamerDouglasPeucker($track)));

        // test init with epsilon=0.01
        $rdp = new phpRdp('point.lat', 'point.lon', 0.1);
        $this->assertSame(4, count($rdp->RamerDouglasPeucker($track)));

        // test init with epsilon=1
        $rdp = new phpRdp('point.lat', 'point.lon', 1);
        $this->assertSame(2, count($rdp->RamerDouglasPeucker($track)));

    }
}
