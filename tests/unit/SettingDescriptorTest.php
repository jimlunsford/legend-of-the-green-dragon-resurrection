<?php
use PHPUnit\Framework\TestCase;
use Resurrection\Http\SettingDescriptor;
final class SettingDescriptorTest extends TestCase {
    public function testDeclaredTypesAndBounds(): void {
        $schema=SettingDescriptor::declare(['Section,title','yes'=>'Enabled,bool|1','count'=>'Count,int|0','bounded'=>'Bounded,range,1,20,1|10','decimal'=>'Modifier,floatrange,.1,2,.05|.75','choice'=>'Mode,enum,a,First,b,Second|a','text'=>'Name','hidden'=>'Read only,viewonly']);
        self::assertCount(6,$schema);
        self::assertSame('1',$schema['yes']['default']);
        foreach (['yes'=>'0','count'=>'2147483647','bounded'=>'20','decimal'=>'.75','choice'=>'b','text'=>"O'Reilly \\ <script>é"] as $key=>$value) self::assertSame($value,SettingDescriptor::value($schema[$key],$value));
        foreach ([['yes','true'],['yes','2'],['count','1e2'],['count','2147483648'],['bounded','0'],['bounded','21'],['decimal','INF'],['decimal','2.1'],['choice','c'],['text',str_repeat('x',256)],['text',[]],['text',"bad\0text"]] as [$key,$value]) {
            try { SettingDescriptor::value($schema[$key],$value); self::fail('Accepted invalid value.'); } catch (InvalidArgumentException $error) { self::assertNotSame('',$error->getMessage()); }
        }
        self::assertSame(['yes'=>'0'],SettingDescriptor::patch($schema,['csrf_token'=>'x','action_token'=>'y','yes'=>'0']));
        $this->expectException(InvalidArgumentException::class);
        SettingDescriptor::patch($schema,['undeclared'=>'1']);
    }
    public function testUnsupportedDescriptorsFailClosed(): void {
        $this->expectException(DomainException::class);
        SettingDescriptor::declare(['arbitrary'=>'Code,executable']);
    }
}
