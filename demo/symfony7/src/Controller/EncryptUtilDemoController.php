<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Demo page: EncryptUtil (encrypt/decrypt) + Twig filter (decrypt).
 */
class EncryptUtilDemoController extends AbstractController
{
    #[Route('/encrypt-util-demo', name: 'encrypt_util_demo')]
    public function index(EncryptUtil $encryptUtil): Response
    {
        // --- Default config (null) ---
        $plainDefault     = 'Secret text with default config';
        $encryptedDefault = $encryptUtil->encrypt($plainDefault);
        $decryptedDefault = $encryptUtil->decrypt($encryptedDefault);

        // --- Specified config: personal_data ---
        $plainPersonal     = 'Secret with personal_data config';
        $encryptedPersonal = $encryptUtil->encrypt($plainPersonal, 'personal_data');
        $decryptedPersonal = $encryptUtil->decrypt($encryptedPersonal, 'personal_data');

        // --- Specified config: financial_data ---
        $plainFinancial     = 'Secret with financial_data config';
        $encryptedFinancial = $encryptUtil->encrypt($plainFinancial, 'financial_data');
        $decryptedFinancial = $encryptUtil->decrypt($encryptedFinancial, 'financial_data');

        // --- For Twig filter: default and specified config (same and different from default) ---
        $encryptedForTwigDefault   = $encryptUtil->encrypt('Decrypted in Twig with |decrypt (default)');
        $encryptedForTwigPersonal  = $encryptUtil->encrypt('Decrypted with |decrypt(\'personal_data\')', 'personal_data');
        $encryptedForTwigFinancial = $encryptUtil->encrypt('Decrypted with |decrypt(\'financial_data\')', 'financial_data');

        return $this->render('encrypt_util_demo/index.html.twig', [
            'plainDefault'              => $plainDefault,
            'encryptedDefault'          => $encryptedDefault,
            'decryptedDefault'          => $decryptedDefault,
            'plainPersonal'             => $plainPersonal,
            'encryptedPersonal'         => $encryptedPersonal,
            'decryptedPersonal'         => $decryptedPersonal,
            'plainFinancial'            => $plainFinancial,
            'encryptedFinancial'        => $encryptedFinancial,
            'decryptedFinancial'        => $decryptedFinancial,
            'encryptedForTwigDefault'   => $encryptedForTwigDefault,
            'encryptedForTwigPersonal'  => $encryptedForTwigPersonal,
            'encryptedForTwigFinancial' => $encryptedForTwigFinancial,
        ]);
    }
}
