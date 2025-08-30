<?php 
namespace Drupal\ncbs\Controller;
use Drupal\ncbs\Service\PdfGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;

use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Response;



class PdfDownloaderController extends ControllerBase {

  protected PdfGenerator $pdfGenerator;

  public function __construct(PdfGenerator $pdf_generator) {
    $this->pdfGenerator = $pdf_generator;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ncbs.pdf_generator')
    );
  }

  public function download($nid) {
    $node = Node::load($nid);
    if (!$node || $node->getType() !== 'submit_application') {
      return new Response("Node not found or invalid type.", 404);
    }


    $node = Node::load($nid);
$title = $node->getTitle();
$term = $node->get('field_program_name')->entity;
$program_name = $term ? $term->label() : 'unknown';

// 2) Sanitize the filename (replace unsafe chars with underscores).
$base_name = preg_replace('/[^A-Za-z0-9\-_]/', '_', $title . '-' . $program_name);
$filename = $base_name;


    $pdf_bytes = $this->pdfGenerator->build($node);

    return new Response($pdf_bytes, 200, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
    ]);
  }
}
