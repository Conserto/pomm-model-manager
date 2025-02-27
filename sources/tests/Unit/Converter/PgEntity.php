<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Test\Unit\Converter;

use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Converter\PgHstore;
use PommProject\ModelManager\Model\RowStructure;
use PommProject\ModelManager\Test\Fixture\ComplexFixture;
use PommProject\ModelManager\Test\Fixture\ComplexFixtureModel;
use PommProject\ModelManager\Test\Fixture\ComplexFixtureStructure;
use PommProject\ModelManager\Test\Fixture\ComplexNumber;
use PommProject\ModelManager\Test\Fixture\ComplexNumberStructure;
use PommProject\ModelManager\Test\Unit\BaseTest;

class PgEntity extends BaseTest
{
    protected function initializeSession(Session $session): void
    {
        parent::initializeSession($session);
        $session
            ->getPoolerForType('converter')
            ->getConverterHolder()
            ->registerConverter('HStore', new PgHstore(), ['hstore'])
            ;
    }

    protected function getComplexNumberConverter()
    {
        return $this->newTestedInstance(
            \PommProject\ModelManager\Test\Fixture\ComplexNumber::class,
            new ComplexNumberStructure()
        );
    }

    protected function getComplexFixtureConverter()
    {
        return $this->newTestedInstance(
            ComplexFixture::class,
            new ComplexFixtureStructure()
        );
    }

    public function testFromPg(): void
    {
        $this
            ->assert("Row types are converted into entities.")
            ->given(
                $entity = $this->getComplexNumberConverter()->fromPg(
                    '(1.233,2.344)',
                    'complex_number',
                    $this->setUpSession($this->buildSession())
                )
            )
                ->object($entity)
                    ->isInstanceOf(\PommProject\ModelManager\Test\Fixture\ComplexNumber::class)
                ->float($entity['real'])
                    ->isEqualTo(1.233)
                ->float($entity['imaginary'])
                    ->isEqualTo(2.344)
            ->assert("Null values return null.")
            ->given(
                $result = $this->getComplexNumberConverter()->fromPg(
                    null,
                    'complex_number',
                    $this->setUpSession($this->buildSession())
                )
            )
                ->variable($result)
                    ->isNull()
            ;
    }

    public function testComplexFromPg(): void
    {
        $converter = $this->getComplexFixtureConverter();
        $session = $this->setUpSession($this->buildSession());
        $entity = $converter->fromPg(
                '(1,,"(1.233,2.344)","{""(3.455,4.566)"",""(5.677,6.788)"",NULL}","2014-10-24 12:44:40.021324+00","{""1982-04-21 23:12:43+00""}")',
                'complex_fixture',
                $session
            );

        $this
            ->object($entity)
            ->isInstanceOf(ComplexFixture::class)
            ->integer($entity['id'])
            ->isEqualTo(1)
            ->object($entity['complex_number'])
            ->isInstanceOf(\PommProject\ModelManager\Test\Fixture\ComplexNumber::class)
            ->array($entity['complex_numbers'])
            ->hasSize(3)
            ->variable($entity['version_id'])
            ->isNull()
            ->variable($converter->fromPg('', 'complex_fixture', $session))
            ->isNull()
            ;
        $converter = $this->newTestedInstance(
            ComplexFixture::class,
            (new RowStructure())
            ->setRelation('some_type')
            ->addField('a_field', 'int4')
            ->addField('a_null_field', 'bool')
            ->addField('some_fields', 'int4[]')
            ->addField('a_hstore', 'hstore')
        );
        $line = <<<"ROW"
(34,,"{4,3}","""pika"" => ""\\\\\\"chu, rechu""")
ROW;
        $entity = $converter->fromPg($line, 'some_type', $session);
        $this
            ->object($entity)
            ->isInstanceOf(ComplexFixture::class)
            ->integer($entity['a_field'])
            ->isEqualTo(34)
            ->variable($entity['a_null_field'])
            ->isNull()
            ->array($entity['some_fields'])
            ->isIdenticalTo([4, 3])
            ->array($entity['a_hstore'])
            ->isIdenticalTo(['pika' => '\\"chu, rechu'])
            ;
    }

    public function testFromPgWithJson(): void
    {
        $session = $this->setUpSession($this->buildSession());
        $converter = $this->newTestedInstance(
            ComplexFixture::class,
            (new RowStructure())
            ->setRelation('some_type')
            ->addField('a_field', 'int4')
            ->addField('a_null_field', 'bool')
            ->addField('a_json', 'jsonb')
        );
        $line = <<<"ROW"
(34,,"{""a"": {""b"": ""c\\\\""pika\\\\""""}}")
ROW;
        $entity = $converter->fromPg($line, 'some_type', $session);
        $this
            ->object($entity)
            ->array($entity['a_json'])
            ->isIdenticalTo(['a' => ['b' => 'c"pika"']])
            ;
    }

    public function testToPg($complex_fixture): void
    {
        $converter = $this->getComplexFixtureConverter();
        $session = $this->setUpSession($this->buildSession());
        $string = $converter->toPg($complex_fixture, 'complex_fixture', $session);

        $this
            ->string($string)
            ->isEqualTo("row(int4 '1',NULL::int4,row(float8 '1.233',float8 '2.344')::pomm_test.complex_number,ARRAY[row(float8 '3.455',float8 '4.566')::pomm_test.complex_number,row(float8 '5.677',float8 '6.788')::pomm_test.complex_number,NULL::pomm_test.complex_number]::pomm_test.complex_number[],timestamptz '2014-10-24 12:44:40.021324+00:00',ARRAY[timestamptz '1982-04-21 23:12:43.000000+00:00']::timestamptz[])::complex_fixture")
            ->string($converter->toPg(null, 'complex_fixture', $session))
            ->isEqualTo('NULL::complex_fixture')
            ;
    }

    protected function testToPgDataProvider()
    {
        return [
            new ComplexFixture([
                'id' => 1,
                'version_id' => null,
                'complex_number' => new ComplexNumber(['real' => 1.233, 'imaginary' => 2.344]),
                'complex_numbers' =>
                    [
                        new ComplexNumber(['real' => 3.455, 'imaginary' => 4.566]),
                        new ComplexNumber(['real' => 5.677, 'imaginary' => 6.788]),
                        null,
                    ],
                'created_at' => new \DateTime('2014-10-24 12:44:40.021324+00'),
                'updated_at' => [new \DateTime('1982-04-21 23:12:43+00')]
            ]),
        ];
    }

    public function testInvalidDataToPg(): void
    {
        $this->exception(function (): void {
            $converter = $this->getComplexFixtureConverter();
            $session = $this->setUpSession($this->buildSession());
            $invalidData = new \stdClass();
            $converter->toPg($invalidData, 'complex_fixture', $session);
        });
    }

    /**
     * @dataProvider testToPgDataProvider
     */
    public function testToPgStandardFormat($complex_fixture): void
    {
        $converter          = $this->getComplexFixtureConverter();
        $session            = $this->setUpSession($this->buildSession());
        $row                = '(1,,"(1.233,2.344)","{""(3.455,4.566)"",""(5.677,6.788)"",NULL}","2014-10-24 12:44:40.021324+00:00","{""1982-04-21 23:12:43.000000+00:00""}")';
        $session->getModel(ComplexFixtureModel::class);

        $this
            ->variable($converter->toPgStandardFormat(null, 'complex_fixture', $session))
            ->isNull()
            ->string($converter->toPgStandardFormat($complex_fixture, 'complex_fixture', $session))
            ->isEqualTo($row)
            ->object($this->sendAsPostgresParameter($complex_fixture, 'complex_fixture', $session))
            ->isInstanceOf(ComplexFixture::class)
            ;
    }

    private function sendAsPostgresParameter($value, string $type, Session $session): mixed
    {
        $result = $session
            ->getQueryManager()
            ->query(
                sprintf("select $*::%s as my_test", $type),
                [$value]
            )
            ->current()
            ;

        return $result['my_test'];
    }
}
