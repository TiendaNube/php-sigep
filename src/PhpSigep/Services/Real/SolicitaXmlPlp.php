<?php
namespace PhpSigep\Services\Real;

use PhpSigep\Model\SolicitaXmlPlpResult;
use PhpSigep\Services\Exception;
use PhpSigep\Services\Result;
use PhpSigep\Bootstrap;
use PhpSigep\Services\Real\Exception\SolicitaXmlPlp\FailedConvertToArrayException;
use PhpSigep\Services\Real\Exception\SolicitaXmlPlp\FailedConvertXmlException;
use PhpSigep\Services\Real\Exception\SolicitaXmlPlp\FailedResultException;

/**
 * @author: Cristiano Soares
 * @link: http://comerciobr.com
 */
class SolicitaXmlPlp
{
    /**
     * @param integer $idPlpMaster
     *
     * @throws \PhpSigep\Services\Exception
     * @return Result<SolicitaXmlPlpResult[]>
     */
    public function execute($idPlpMaster)
    {
        $soapArgs = array(
            'idPlpMaster'   => $idPlpMaster,
            'usuario'        => Bootstrap::getConfig()->getAccessData()->getUsuario(),
            'senha'          => Bootstrap::getConfig()->getAccessData()->getSenha()
        );

        $result = new Result();

        try {
            $r = SoapClientFactory::getSoapClient()->solicitaXmlPlp($soapArgs);
            if (!$r || !is_object($r) || !isset($r->return) || ($r instanceof \SoapFault)) {
                if ($r instanceof \SoapFault) {
                    throw $r;
                }

                throw new FailedResultException('Erro ao consultar XML da PLP. Retorno: "' . $r . '"');
            }
            if (is_string($r->return)) {
                $xml = $this->load_xml_from_string($r->return);

                if ($xml instanceof \SimpleXMLElement) {
                    $object = json_decode(json_encode($xml), true);
                    $objectFormatted = $this->remove_empty_fields($object);

                    if ($objectFormatted) {
                        $result->setResult(new SolicitaXmlPlpResult($objectFormatted));
                    } else {
                        throw new FailedConvertToArrayException('Erro ao converter Object para Array da PLP. Retorno: "' . print_r(json_last_error_msg(), true) . '"');
                    }
                } else {
                    throw new FailedConvertXmlException('Erro ao converter XML da PLP. Retorno: "' . print_r(libxml_get_errors(), true) . '"');
                }
            } else {
                throw new FailedResultException('Erro no resultado do XML da PLP. Retorno: "' . print_r($r->return, true) . '"');
            }
        } catch (\Exception $e) {
            if ($e instanceof \SoapFault) {
                $result->setIsSoapFault(true);
                $result->setErrorCode($e->getCode());
                $result->setErrorMsg("Resposta do Correios: " . SoapClientFactory::convertEncoding($e->getMessage()));
            } else {
                $result->setErrorCode($e->getCode());
                $result->setErrorMsg($e->getMessage());
            }
        }

        return $result;
    }

    /*
    * This function was create to solve the problem with converse some xml sended by correios.
    * The first step is try the common flow if get error the function will try just load the xml.
    */
    private function load_xml_from_string($str){
        try {
            $xmlString = iconv('utf-8', 'ISO-8859-1//IGNORE', $str);
            $xml = simplexml_load_string($xmlString, \SimpleXMLElement::class, LIBXML_NOCDATA);
        } catch(\Exception $e) {
            $xml = simplexml_load_string($str, \SimpleXMLElement::class, LIBXML_NOCDATA);
        }

        return $xml;
    }

    /*
    * This function runs recursively for all object key and remove empty values
    */
    private function remove_empty_fields($input){
        foreach ($input as &$value){
            if (is_array($value)){
                $value = $this->remove_empty_fields($value);
            }
        }
        return array_filter($input);
    }
}
