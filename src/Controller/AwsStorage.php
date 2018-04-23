<?php

namespace App\Controller;

use App\Command\CommandDispatcherTrait;
use App\Service\ExcelExport;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use League\Flysystem\Adapter\AwsS3 as Adapter;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\Request; 
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Aws\Credentials\CredentialProvider;

class AwsStorage extends AbstractController
{
    use CommandDispatcherTrait;

    /**
     * @Route("/cfdoc/aws", name="aws_storage_file")
     *
     * 
     */
    public function awsStorage(Request $request)
    {
        $form = $this->createFormBuilder() 
        ->add('upload', FileType::class, array('label' => 'File Upload')) 
        ->add('save', SubmitType::class, array('label' => 'Submit'))
        ->getForm(); 

        $form->handleRequest($request); 
        $filesystem = $this->configuration();
        
        if ($form->isSubmitted() && $form->isValid()) { 
            $data = $form->getData();
            $fileName = $data['upload']->getClientOriginalName(); 
            $file = $data['upload']->getRealPath(); 
            if ($filesystem->has($fileName))
            {echo '<div class="message">Already exists</div>';}
            else
            {
                $stream = fopen($file, 'r+');
                $result =$filesystem->writeStream($fileName, $stream);
                fclose($stream);
                echo '<div class="message">File Uploaded Successfully..!!</div>';
            }
        }
       $aws_data = $filesystem->listContents();
        return $this->render('framework/doc_tree/aws_upload.html.twig', array( 
            'form' => $form->createView(),
            'aws_files' => $aws_data,
        ));
    }

    private function configuration()
    {
        $provider = CredentialProvider::defaultProvider();
        $client = \Aws\S3\S3Client::factory([
        'credentials' => $provider,
        'region'  => 'us-east-1',
        'version' => 'latest',
        ]); 

        $adapter    = new AwsS3Adapter($client, 'actinc.opensalt.np', 'dev');
        $filesystem = new Filesystem($adapter);
        return $filesystem;
    }

/**
 * @param String $fileName
 *
 * @Route("/{fileName}/file-download", requirements={"fileName"=".+"}, name="aws_file_download")
 * @return StreamedResponse
 *
 */
    public function awsDownload(String $fileName): StreamedResponse
    {
        $filesystem = $this->configuration();
        $stream = $filesystem->readStream($fileName);
        $contents = stream_get_contents($stream);

        return new StreamedResponse(
            function () use ($contents, $fileName, $filesystem) {
                $local = fopen('php://output', 'rw+');
                fwrite($local, $contents);
                fclose($local);
            },
            200,
            [
                'Content-Type' => $filesystem->getMimetype($fileName),
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }
}