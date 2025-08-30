<?php

// namespace Drupal\ncbs_csv\Controller;

// use Symfony\Component\HttpFoundation\BinaryFileResponse;
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// use PhpOffice\PhpSpreadsheet\Style\Alignment;
// use Drupal\Core\Controller\ControllerBase;
// use Drupal\views\Views;
// use Drupal\node\Entity\Node;

// /**
//  * Controller to export all display pages of view_application.
//  */
// class CsvAllController extends ControllerBase {

//   public function exportAllDisplays() {
//     $display_ids = ['all_applications', 
//                     'current_applications',
//                     'new_applications',
//                     'comments_recommendations_requested_applications', 
//                     'invited_to_visit_invited_applicant_applications',
//                     'prescreen_interview',
//                     'allready_visited_invited_applicant',
//                     'ready_to_present_to_faculty',
//                     'allready_presented_to_faculty',
//                     'ready_for_assessment',
//                     'offer_issued',
//                     'accepted',
//                     'selected',
//                     'not_accepted',
//                     'joined',
//                     'closed'
//                   ]; 
//     $spreadsheet = new Spreadsheet();
//     $firstSheet = true;

//     foreach ($display_ids as $display_id) {
//       $view = Views::getView('view_application');
//       if (!$view || !$view->setDisplay($display_id)) {
//         continue;
//       }

//       $view->execute();

//       $sheet = $firstSheet ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
//       // $sheet->setTitle($display_id);

//       $sheet->setTitle(substr(preg_replace('/[^a-zA-Z0-9_]/', '_', $display_id), 0, 31));

//       $firstSheet = false;

//       // Headers
//       $headers = ['Sr No', 'Name', 'Research Proposal', 'Basic Qualification And Experience', 'Faculty Comment', 'General Comments'];
//       $col = 'A';
//       foreach ($headers as $header) {
//         $sheet->setCellValue($col . '1', $header);
//         $sheet->getStyle($col . '1')->getFont()->setBold(true);
//         $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
//         $col++;
//       }

//       $sheet->getDefaultRowDimension()->setRowHeight(15);
//       foreach (range('A', 'F') as $columnID) {
//         $sheet->getColumnDimension($columnID)->setAutoSize(true);
//       }

//       $rowNumber = 2;
//       $sr_no = 1;
//       foreach ($view->result as $row) {
//         $entity = $row->_entity;
//         $name = $entity->label();

//         // --- Research Proposal ---
//         $ticked_options = 'N/A';
//         if ($entity->hasField('field_other_relevant_info_ref') && !$entity->get('field_other_relevant_info_ref')->isEmpty()) {
//           $ref_id = $entity->get('field_other_relevant_info_ref')->target_id;
//           $ref_entity = Node::load($ref_id);
//           if ($ref_entity && $ref_entity->hasField('field_please_tick_one_or_more_of')) {
//             $vals = [];
//             foreach ($ref_entity->get('field_please_tick_one_or_more_of')->getValue() as $val) {
//               if (!empty($val['value'])) {
//                 $vals[] = $val['value'];
//               }
//             }
//             $ticked_options = !empty($vals) ? implode("\n", $vals) : 'N/A';
//           }
//         }

//         // --- Academic + Work Experience ---
//         $academic_details = [];
//         $work_experience_details = [];

//         if ($entity->hasField('field_academic_qualification_ref') && !$entity->get('field_academic_qualification_ref')->isEmpty()) {
//           $ref_node = Node::load($entity->get('field_academic_qualification_ref')->target_id);
//           if ($ref_node && $ref_node->hasField('field_academic_qualification')) {
//             foreach ($ref_node->get('field_academic_qualification')->referencedEntities() as $para) {
//               $academic_details[] = "" . ($para->get('field_year_of_pass')->value ?? 'N/A') . "\n"
//                 . "" . ($para->get('field_qualification_degree')->value ?? 'N/A') . "\n"
//                 . "" . ($para->get('field_institution')->value ?? 'N/A') . "\n"
//                 . "" . ($para->get('field_university')->value ?? 'N/A');
//             }
//           }
//         }

//         if ($entity->hasField('field_work_experience_ref') && !$entity->get('field_work_experience_ref')->isEmpty()) {
//           $ref_node = Node::load($entity->get('field_work_experience_ref')->target_id);
//           if ($ref_node && $ref_node->hasField('field_work_experience')) {
//             foreach ($ref_node->get('field_work_experience')->referencedEntities() as $para) {
//               $work_experience_details[] = "Designation: " . ($para->get('field_designation')->value ?? 'N/A') . "\n"
//                 . "" . ($para->get('field_institute')->value ?? 'N/A') . "\n"
//                 . "" . ($para->get('field_lab_name')->value ?? 'N/A') ;
//                 // . "" . ($para->get('field_year')->value ?? 'N/A');
//             }
//           }
//         }

//         $basic_qualification_experience = "Education:\n" . (!empty($academic_details) ? implode("\n\n", $academic_details) : 'N/A')
//           . "\n\nWork Experience:\n" . (!empty($work_experience_details) ? implode("\n\n", $work_experience_details) : 'N/A');

//         // --- Comment fetching closure ---
//         $fetch_comments = function ($entity, $field_name) {
//           $output = [];

//           $role_field_map = [
//             'field_faculty_member_comment_ref' => 'field_user_list_faculty',
//             'field_dean_comment_reference' => 'field_user_list_dean',
//           ];
//           $user_field = $role_field_map[$field_name] ?? null;

//           if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
//             foreach ($entity->get($field_name)->referencedEntities() as $ref_node) {
//               $admin_author = null;
//               if ($field_name === 'field_admin_comment_reference' && $ref_node->hasField('field_comment_name')) {
//                 $admin_author = $ref_node->get('field_comment_name')->value ?? 'Unknown Admin';
//               }

//               if ($ref_node->hasField('field_add_comments_') && !$ref_node->get('field_add_comments_')->isEmpty()) {
//                 foreach ($ref_node->get('field_add_comments_')->referencedEntities() as $para) {
//                   $author = 'Unknown User';
//                   if ($field_name === 'field_admin_comment_reference') {
//                     $author = $admin_author ?? 'Unknown Admin';
//                   } elseif ($user_field && $para->hasField($user_field) && !$para->get($user_field)->isEmpty()) {
//                     $author = $para->get($user_field)->entity->label();
//                   }

//                   $comment_date = $para->get('field_comment_date')->value ?? 'No Date';
//                   $comment_text = $para->get('field_comments')->value ?? 'No Comment';
//                   $output[] = "$author\nDate: $comment_date\nComment: $comment_text";
//                 }
//               }
//             }
//           }

//           return !empty($output) ? implode("\n\n", $output) : 'N/A';
//         };

//         $faculty_comments = $fetch_comments($entity, 'field_faculty_member_comment_ref');
//         $general_comments = $fetch_comments($entity, 'field_admin_comment_reference') . "\n\n" . $fetch_comments($entity, 'field_dean_comment_reference');

//         // --- Write to sheet ---
//         $sheet->setCellValue("A$rowNumber", $sr_no++);
//         $sheet->setCellValue("B$rowNumber", $name);
//         $sheet->setCellValue("C$rowNumber", $ticked_options);
//         $sheet->setCellValue("D$rowNumber", $basic_qualification_experience);
//         $sheet->setCellValue("E$rowNumber", $faculty_comments);
//         $sheet->setCellValue("F$rowNumber", $general_comments);

//         $sheet->getStyle("C$rowNumber:F$rowNumber")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
//         $rowNumber++;
//       }
//     }

//     // Save and return
//     $file_name = 'view_application_all_displays.xlsx';
//     $file_path = \Drupal::service('file_system')->getTempDirectory() . '/' . $file_name;
//     $writer = new Xlsx($spreadsheet);
//     $writer->save($file_path);

//     return new BinaryFileResponse($file_path, 200, [
//       'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
//       'Content-Disposition' => 'attachment; filename="' . $file_name . '"',
//     ]);
//   }

// }





































namespace Drupal\ncbs_csv\Controller;


 use Drupal\Core\Controller\ControllerBase;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Drupal\views\Views;
use Drupal\node\Entity\Node;



class CsvAllController extends ControllerBase {
public function exportAllDisplays(Request $request) {
  $display_ids = [
    'all_applications', 
    'applications_on_hold',
    'new_applications',
    'recommendations_requested_applications', 
    'invited_to_visit_invited_applicant_applications',
    'prescreen_interview',
    'allready_visited_invited_applicant',
    'ready_to_present_to_faculty',
    'allready_presented_to_faculty',
    'ready_for_assessment',
    'offer_issued',
    'accepted',
    'selected',
    'not_accepted',
    'joined',
    'closed'
  ];

  $spreadsheet = new Spreadsheet();
  $spreadsheet->removeSheetByIndex(0); // Remove default sheet

  foreach ($display_ids as $display_id) {
    $view = Views::getView('view_application');
    if (!$view || !$view->setDisplay($display_id)) {
      continue; // Skip invalid view/display
    }

    $view->execute();
    $data_by_parent = [];

    foreach ($view->result as $row) {
      $entity = $row->_entity;
      $name = $entity->label();
      $ticked_options = 'N/A';
      $parent_label = 'Uncategorized';

      // Same logic for taxonomy grouping
      if ($entity->hasField('field_other_relevant_info_ref') && !$entity->get('field_other_relevant_info_ref')->isEmpty()) {
        $ref_id = $entity->get('field_other_relevant_info_ref')->target_id;
        $ref_entity = Node::load($ref_id);
        if ($ref_entity && $ref_entity->hasField('field_new_research_areas') && !$ref_entity->get('field_new_research_areas')->isEmpty()) {
          $selected_tids = array_column($ref_entity->get('field_new_research_areas')->getValue(), 'target_id');
          $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
          $selected_terms = $term_storage->loadMultiple($selected_tids);

          $grouped = [];
          foreach ($selected_terms as $term) {
            if (!$term instanceof \Drupal\taxonomy\Entity\Term) continue;

            $term_label = $term->label();
            $term_id = $term->id();
            $parents = $term->get('parent')->referencedEntities();
            if (!empty($parents)) {
              $parent = $parents[0];
              $pid = $parent->id();
              $parent_label = $parent->label();
              $grouped[$pid]['label'] = $parent_label;
              $grouped[$pid]['children'][] = $term_label;
            } else {
              $child_tree = $term_storage->loadTree($term->bundle(), $term_id, 1, TRUE);
              $child_labels = [];
              foreach ($child_tree as $child) {
                if (in_array($child->id(), $selected_tids)) {
                  $child_labels[] = $child->label();
                }
              }
              $parent_label = $term_label;
              $grouped[$term_id]['label'] = $term_label;
              $grouped[$term_id]['children'] = $child_labels;
            }
          }

          if (!empty($grouped)) {
            $lines = [];
            foreach ($grouped as $entry) {
              $lines[] = $entry['label'];
              if (!empty($entry['children'])) {
                foreach ($entry['children'] as $child) {
                  $lines[] = '- ' . $child;
                }
              }
            }
            $ticked_options = implode("\n", $lines);
          }
        }
      }

// Academic Qualification
$academic_details = [];
if ($entity->hasField('field_academic_qualification_ref') && !$entity->get('field_academic_qualification_ref')->isEmpty()) {
  $ref_node = Node::load($entity->get('field_academic_qualification_ref')->target_id);
  if ($ref_node && $ref_node->hasField('field_academic_qualification')) {
    foreach ($ref_node->get('field_academic_qualification')->referencedEntities() as $para) {
      $year = $para->get('field_year_of_pass')->value ?? 'N/A';
      $degree = $para->get('field_qualification_degree')->value ?? 'N/A';
      $institution = $para->get('field_institution')->value ?? 'N/A';
      $university = $para->get('field_university')->value ?? 'N/A';

      $academic_details[] = "{$year}, {$degree}, {$institution}, {$university}";
    }
  }
}

// Work Experience
$work_experience_details = [];
if ($entity->hasField('field_work_experience_ref') && !$entity->get('field_work_experience_ref')->isEmpty()) {
  $ref_node = Node::load($entity->get('field_work_experience_ref')->target_id);
  if ($ref_node && $ref_node->hasField('field_work_experience')) {
    foreach ($ref_node->get('field_work_experience')->referencedEntities() as $para) {
      $from_date_raw = $para->get('field_from_date')->value ?? null;
      $to_date_raw = $para->get('field_to_date')->value ?? null;

      $from_date = $from_date_raw ? \DateTime::createFromFormat('Y-m-d', $from_date_raw)->format('d-m-Y') : 'N/A';
      $to_date = $to_date_raw ? \DateTime::createFromFormat('Y-m-d', $to_date_raw)->format('d-m-Y') : 'N/A';
      $designation = $para->get('field_designation')->value ?? 'N/A';
      $institute = $para->get('field_institute')->value ?? 'N/A';
      $lab_name = $para->get('field_lab_name')->value ?? 'N/A';

      $work_experience_details[] = "{$from_date} to {$to_date}, {$designation}, {$institute}, {$lab_name}";
    }
  }
}

$basic_qualification_experience = "Education:\n" . (!empty($academic_details) ? implode("\n", $academic_details) : 'N/A')
  . "\n\nWork Experience:\n" . (!empty($work_experience_details) ? implode("\n", $work_experience_details) : 'N/A');

      $fetch_comments = function ($entity, $field_name) {
        $output = [];
        $role_field_map = [
          'field_faculty_member_comment_ref' => 'field_user_list_faculty',
          'field_dean_comment_reference' => 'field_user_list_dean',
        ];

        $comment_name_fields = [
          'field_admin_comment_reference',
          'field_prescreen_comment_ref',
          'field_faculty_search_comit_coref',
        ];

        if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
          foreach ($entity->get($field_name)->referencedEntities() as $ref_node) {
            $admin_author = in_array($field_name, $comment_name_fields) && $ref_node->hasField('field_comment_name')
              ? $ref_node->get('field_comment_name')->value
              : null;

            if ($ref_node->hasField('field_add_comments_') && !$ref_node->get('field_add_comments_')->isEmpty()) {
              $entries = [];
              foreach ($ref_node->get('field_add_comments_')->referencedEntities() as $para) {
                try {
                  $author = $admin_author ?? 'Unknown';
                  if (!in_array($field_name, $comment_name_fields)) {
                    $user_field = $role_field_map[$field_name] ?? null;
                    if ($user_field && $para->hasField($user_field) && !$para->get($user_field)->isEmpty()) {
                      $author = $para->get($user_field)->entity->label();
                    }
                  }

                  $comment_date = $para->get('field_comment_date')->value ?? 'No Date';
                  $comment_text = $para->get('field_comments')->value ?? 'No Comment';

                  $entries[] = [
                    'date' => $comment_date,
                    'text' => "$author\nDate: $comment_date\nComment: $comment_text",
                  ];
                } catch (\Exception $e) {}
              }

              usort($entries, fn($a, $b) => strcmp($b['date'], $a['date']));
              foreach ($entries as $entry) {
                $output[] = $entry['text'];
              }
            }
          }
        }

        return !empty($output) ? implode("\n\n", $output) : 'N/A';
      };

      $faculty_comments = $fetch_comments($entity, 'field_faculty_member_comment_ref');
      $general_comments = $fetch_comments($entity, 'field_admin_comment_reference') . "\n\n" . $fetch_comments($entity, 'field_dean_comment_reference');
      $prescreen_comments = $fetch_comments($entity, 'field_prescreen_comment_ref');
      $faculty_search_comments = $fetch_comments($entity, 'field_faculty_search_comit_coref');

      $data_by_parent[$parent_label][] = [
        'Name' => $name,
        'ResearchProposal' => $ticked_options,
        'BasicQualification' => $basic_qualification_experience,
        'FacultyComment' => $faculty_comments,
        'GeneralComments' => $general_comments,
        'PrescreenComments' => $prescreen_comments,
        'SearchCommitteeComments' => $faculty_search_comments,
      ];
    }

    ksort($data_by_parent);

    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(substr($display_id, 0, 31)); // Excel max 31 chars

    $headers = [
      'Sr No',
      'Name',
      'Research Proposal',
      'Basic Qualification And Experience',
      'Faculty Comment',
      'General Comments',
      'Prescreen Comments',
      'Faculty Search Comments',
    ];

    $boldStyle = ['font' => ['bold' => true]];
    $sheet->getDefaultRowDimension()->setRowHeight(15);

    foreach (range('A', 'H') as $columnID) {
      $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    $rowNumber = 1;
    $sr_no = 1;

    foreach ($data_by_parent as $parent_label => $entries) {
      $sheet->mergeCells("A$rowNumber:H$rowNumber");
      $sheet->setCellValue("A$rowNumber", $parent_label);
      $sheet->getStyle("A$rowNumber")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
      ]);
      $rowNumber++;

      $col = 'A';
      foreach ($headers as $header) {
        $sheet->setCellValue($col . $rowNumber, $header);
        $sheet->getStyle($col . $rowNumber)->applyFromArray($boldStyle);
        $sheet->getStyle($col . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $col++;
      }
      $rowNumber++;

      foreach ($entries as $entry) {
        $sheet->setCellValue("A$rowNumber", $sr_no++);
        $sheet->setCellValue("B$rowNumber", $entry['Name']);
        $sheet->setCellValue("C$rowNumber", $entry['ResearchProposal']);
        $sheet->setCellValue("D$rowNumber", $entry['BasicQualification']);
        $sheet->setCellValue("E$rowNumber", $entry['FacultyComment']);
        $sheet->setCellValue("F$rowNumber", $entry['GeneralComments']);
        $sheet->setCellValue("G$rowNumber", $entry['PrescreenComments']);
        $sheet->setCellValue("H$rowNumber", $entry['SearchCommitteeComments']);
        $sheet->getStyle("C$rowNumber:H$rowNumber")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $rowNumber++;
      }
      $rowNumber++;
    }
  }

  $file_name = 'all_view_displays_export.xlsx';
  $file_path = \Drupal::service('file_system')->getTempDirectory() . '/' . $file_name;
  $writer = new Xlsx($spreadsheet);
  $writer->save($file_path);

  return new BinaryFileResponse($file_path, 200, [
    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'Content-Disposition' => 'attachment; filename="' . $file_name . '"',
  ]);
}
}