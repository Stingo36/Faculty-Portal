<?php

namespace Drupal\ncbs\Service;

use Drupal\node\Entity\Node;
use setasign\Fpdi\Tcpdf\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class PdfGenerator {

  protected FileSystemInterface $fileSystem;
  protected LoggerInterface $logger;

  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory) {
    $this->fileSystem = $file_system;
    // Grab (or create) a channel named "ncbs":
    $this->logger = $logger_factory->get('ncbs');
  }
  
  public function build(Node $node): string {
    $pdf = new Fpdi();
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Merge CV, proposals, publications, referee feedback, etc.
    $this->mergeReferencedFile($node, 'field_basic_information_referenc', 'field_upload_updated_cv', $pdf);
    $this->mergeParagraphFiles($node, 'field_research_proposal_ref', 'field_research_proposal', 'field_research', $pdf);
    $this->mergeParagraphFiles($node, 'field_update_publications_ref', 'field_update_publications', 'field_publication_document', $pdf);
    $this->mergeRefereeFeedbackFiles($node, 'field_list_of_referees_ref', 'field_list_of_referees_', 'field_referee_feedback_reference', 'field_upload_recommendations', $pdf);

    // Output to string
    return $pdf->Output('', 'S');
  }

  private function mergeReferencedFile(Node $node, string $field_ref, string $field_file, Fpdi $pdf): void {
    if ($node->hasField($field_ref) && !$node->get($field_ref)->isEmpty()) {
      $reference_node = $node->get($field_ref)->entity;
      if ($reference_node && $reference_node->hasField($field_file) && !$reference_node->get($field_file)->isEmpty()) {
        $this->addFileToPdf($reference_node->get($field_file)->entity, $pdf);
      }
    }
  }

  private function mergeParagraphFiles(Node $node, string $field_ref, string $sub_field_ref, string $file_field, Fpdi $pdf): void {
    if ($node->hasField($field_ref) && !$node->get($field_ref)->isEmpty()) {
      foreach ($node->get($field_ref)->referencedEntities() as $paragraph) {
        if ($paragraph->hasField($sub_field_ref) && !$paragraph->get($sub_field_ref)->isEmpty()) {
          foreach ($paragraph->get($sub_field_ref)->referencedEntities() as $sub_paragraph) {
            if ($sub_paragraph->hasField($file_field) && !$sub_paragraph->get($file_field)->isEmpty()) {
              $this->addFileToPdf($sub_paragraph->get($file_field)->entity, $pdf);
            }
          }
        }
      }
    }
  }

  private function mergeRefereeFeedbackFiles(Node $node, string $field_referees_ref, string $field_referees, string $field_feedback_ref, string $file_field, Fpdi $pdf): void {
    if ($node->hasField($field_referees_ref) && !$node->get($field_referees_ref)->isEmpty()) {
      foreach ($node->get($field_referees_ref)->referencedEntities() as $referee_paragraph) {
        if ($referee_paragraph->hasField($field_referees) && !$referee_paragraph->get($field_referees)->isEmpty()) {
          foreach ($referee_paragraph->get($field_referees)->referencedEntities() as $referee) {
            if ($referee->hasField($field_feedback_ref) && !$referee->get($field_feedback_ref)->isEmpty()) {
              foreach ($referee->get($field_feedback_ref)->referencedEntities() as $feedback) {
                if ($feedback->hasField($file_field) && !$feedback->get($file_field)->isEmpty()) {
                  $this->addFileToPdf($feedback->get($file_field)->entity, $pdf);
                }
              }
            }
          }
        }
      }
    }
  }

  private function addFileToPdf($file, Fpdi $pdf): void {
    if (!$file) {
      return;
    }

    $file_path = $this->fileSystem->realpath($file->getFileUri());
    if ($file_path && file_exists($file_path)) {
      try {
        $pageCount = $pdf->setSourceFile($file_path);
        for ($i = 1; $i <= $pageCount; $i++) {
          $tplId = $pdf->importPage($i);
          $pdf->AddPage();
          $pdf->useTemplate($tplId);
        }
      }
      catch (CrossReferenceException | PdfParserException $e) {
        $pdf->AddPage();
        $pdf->MultiCell(0, 10, "There was an issue merging the file:: " . basename($file_path), 0, 'L');
        $this->logger->warning('Skipped PDF file due to error: {message}', ['message' => $e->getMessage()]);
      }
      catch (\Exception $e) {
        $pdf->AddPage();
        $pdf->MultiCell(0, 10, "There was an issue merging the file:: " . basename($file_path), 0, 'L');
        $this->logger->error('Unexpected error when merging PDF: {message}', ['message' => $e->getMessage()]);
      }
    }
  }

}
