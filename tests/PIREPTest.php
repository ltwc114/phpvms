<?php

use App\Models\User;
use App\Models\Pirep;

use Illuminate\Foundation\Testing\WithoutMiddleware;


class PIREPTest extends TestCase
{
    use WithoutMiddleware;
    #use DatabaseMigrations;

    protected $pirepSvc;
    protected $SAMPLE_PIREP
        = [
            'user_id'        => 1,
            'flight_id'      => 1,
            'aircraft_id'    => 1,
            'dpt_airport_id' => 1,
            'arr_airport_id' => 2,
            'flight_time'    => 21600,  # 6 hours
            'level'          => 320,
            'source'         => 0,      # manual
            'notes'          => 'just a pilot report',
        ];

    /**
     * Add $count number of PIREPs and return a User object
     * @param int  $count
     * @param bool $accept
     *
     * @return User
     */
    protected function addPIREP($count=1, $accept=true): User
    {
        for($i = 0; $i < $count; $i++) {
            $pirep = new Pirep($this->SAMPLE_PIREP);
            $pirep = $this->pirepSvc->create($pirep, []);
            if($accept) {
                $this->pirepSvc->changeStatus($pirep,
                    config('enums.pirep_status.ACCEPTED'));
            }
        }

        $pilot = User::where('id', $this->SAMPLE_PIREP['user_id'])->first();
        return $pilot;
    }

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->addData('base');
        $this->pirepSvc = app('App\Services\PIREPService');
    }

    /**
     * @covers \App\Services\PilotService
     * @covers \App\Services\PIREPService
     */
    public function testAddPirep()
    {
        $pirep = new Pirep($this->SAMPLE_PIREP);
        $pirep->save();

        $pirep = $this->pirepSvc->create($pirep, []);

        /**
         * Check the initial status info
         */
        $this->assertEquals($pirep->pilot->flights, 0);
        $this->assertEquals($pirep->status, config('enums.pirep_status.PENDING'));

        /**
         * Now set the PIREP state to ACCEPTED
         */
        $this->pirepSvc->changeStatus($pirep, config('enums.pirep_status.ACCEPTED'));
        $this->assertEquals(1, $pirep->pilot->flights);
        $this->assertEquals(21600, $pirep->pilot->flight_time);
        $this->assertEquals(1, $pirep->pilot->rank_id);

        /**
         * Now go from ACCEPTED to REJECTED
         */
        $this->pirepSvc->changeStatus($pirep, config('enums.pirep_status.REJECTED'));
        $this->assertEquals(0, $pirep->pilot->flights);
        $this->assertEquals(0, $pirep->pilot->flight_time);
        $this->assertEquals(1, $pirep->pilot->rank_id);
        $this->assertEquals($pirep->arr_airport_id, $pirep->pilot->curr_airport_id);
    }

    /**
     * check the stats/ranks, etc have incremented properly
     * @covers \App\Services\PilotService
     * @covers \App\Services\PIREPService
     */
    public function testPilotStatsIncr()
    {
        # Submit two PIREPs
        $pilot = $this->addPIREP(2);
        $last_pirep = Pirep::where('id', $pilot->last_pirep_id)->first();

        $this->assertEquals(2, $pilot->flights);
        $this->assertEquals(43200, $pilot->flight_time);
        $this->assertEquals(2, $pilot->rank_id);
        $this->assertEquals($last_pirep->arr_airport_id, $pilot->curr_airport_id);

        #
        # Submit another PIREP, adding another 6 hours
        # it should automatically be accepted
        #
        $pilot = $this->addPIREP(1, false);
        $latest_pirep = Pirep::where('id', $pilot->last_pirep_id)->first();

        # Make sure latest PIREP was updated
        $this->assertNotEquals($last_pirep->id, $latest_pirep->id);

        # The PIREP should have been automatically accepted
        $this->assertEquals(
            config('enums.pirep_status.ACCEPTED'),
            $latest_pirep->status
        );
    }
}
