<?php

namespace Tests\Unit;

use App\Services\Procurement\ProcurementCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class ProcurementCalculatorTest extends TestCase
{
    public function test_decimal_calculations_vat_exemption_rounding_and_totals(): void
    {
        $calculator = new ProcurementCalculator();
        $line = $calculator->line('12,500000', '3.333333', '17');
        $this->assertSame('41.67', $line['net']);
        $this->assertSame('7.08', $line['vat']);
        $this->assertSame('48.75', $line['gross']);
        $free = $calculator->line('0.1', '0.05', '0');
        $this->assertSame('0.01', $free['net']);
        $this->assertSame('0.00', $free['vat']);
        $this->assertSame(['quantity'=>'12.600000','net'=>'41.68','vat'=>'7.08','gross'=>'48.76'], $calculator->totals([$line, $free]));
        $this->assertSame('11700000000000000000000.00', $calculator->line('100000000000', '100000000000', '17')['gross']);
    }

    /** @dataProvider invalidNumbers */
    public function test_invalid_decimal_input_is_rejected(string $quantity, string $price, string $vat): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ProcurementCalculator())->line($quantity, $price, $vat);
    }

    public function invalidNumbers(): array
    {
        return [['0','1','17'], ['-1','1','17'], ['1','-1','17'], ['1','NaN','17'], ['1','1e2','17'], ['1','1','100.01'], ['1.0000001','1','17'], ['1000000000000','1','17']];
    }

    /** @dataProvider deadlines */
    public function test_local_deadlines_and_terminal_delivery_states(string $due, string $received, bool $cancelled, string $status, int $days, string $label): void
    {
        $calculator = new ProcurementCalculator();
        $purchase = ['deadline'=>$due,'cancelled_at'=>$cancelled ? '2026-10-26' : null, 'items'=>[['quantity'=>'10','received'=>$received]]];
        $this->assertSame($status, $calculator->deliveryStatus($purchase));
        // UTC is still October 26; the business date in Sarajevo is already October 27.
        $deadline = $calculator->deadline($purchase, CarbonImmutable::parse('2026-10-26T23:30:00Z'));
        $this->assertSame($days, $deadline['days']);
        $this->assertSame($label, $deadline['label']);
    }

    public function deadlines(): array
    {
        return [
            ['2026-10-26','0',false,'open',1,'Kasni 1 dan'],
            ['2026-10-27','0',false,'open',0,'Rok danas'],
            ['2026-10-30','0',false,'open',0,'Na vrijeme'],
            ['2026-10-26','4',false,'partial',1,'Kasni 1 dan'],
            ['2026-10-26','10',false,'delivered',0,'Isporučeno'],
            ['2026-10-26','0',true,'cancelled',0,'Otkazano'],
        ];
    }

    public function test_discount_and_line_deadlines_ignore_completed_lines(): void
    {
        $calculator=new ProcurementCalculator();$line=$calculator->line('10','2','17','10');
        $this->assertSame('2.00',$line['discount']);$this->assertSame('18.00',$line['net']);$this->assertSame('21.06',$line['gross']);
        $purchase=['deadline'=>'2026-10-20','items'=>[['quantity'=>'10','received'=>'10','deadline'=>'2026-10-20'],['quantity'=>'5','received'=>'2','deadline'=>'2026-10-30']]];
        $this->assertSame('Na vrijeme',$calculator->deadline($purchase,\Carbon\CarbonImmutable::parse('2026-10-27','Europe/Sarajevo'))['label']);
    }

    public function test_display_formatting_does_not_expose_float_noise(): void
    {
        $fmt=\App\Services\Procurement\ProcurementFormat::class;
        $this->assertSame('2,39',$fmt::quantity('2.3900000000000001','KG'));
        $this->assertSame('1.234,57',$fmt::number('1234.565'));
        $this->assertSame('3',$fmt::quantity('3.000000','KOM'));
    }

    public function test_overdelivery_on_one_line_never_fulfils_another_line(): void
    {
        $purchase=['items'=>[['quantity'=>'5','received'=>'10'],['quantity'=>'5','received'=>'0']]];
        $this->assertSame('partial',(new ProcurementCalculator())->deliveryStatus($purchase));
        $totals=(new ProcurementCalculator())->totals([['quantity'=>'5','net'=>'5','vat'=>'0','gross'=>'5','unit'=>'KG'],['quantity'=>'3','net'=>'3','vat'=>'0','gross'=>'3','unit'=>'KOM']]);
        $this->assertNull($totals['quantity']);$this->assertSame('8.00',$totals['gross']);
    }
}
