<?php

namespace App\Service;

use App\Entity\Inventory;
use App\Repository\ItemRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExportService
{
    public function __construct(
        private ItemRepository $itemRepository,
        private TranslatorInterface $translator
    ) {
    }

    public function exportItemsToCsv(Inventory $inventory): string
    {
        // Получаем все элементы инвентаря без пагинации
        $items = $this->itemRepository->findBy(['inventory' => $inventory], ['customId' => 'ASC']);

        // Заголовки CSV
        $headers = [
            $this->translator->trans('export.custom_id'),
            $this->translator->trans('export.created_at'),
            $this->translator->trans('export.updated_at'),
            $this->translator->trans('export.created_by'),
        ];

        // Добавляем заголовки для кастомных полей
        for ($i = 1; $i <= 3; $i++) {
            if ($inventory->{"getCustomString{$i}State"}()) {
                $headers[] = $inventory->{"getCustomString{$i}Name"}();
            }
            if ($inventory->{"getCustomText{$i}State"}()) {
                $headers[] = $inventory->{"getCustomText{$i}Name"}();
            }
            if ($inventory->{"getCustomInt{$i}State"}()) {
                $headers[] = $inventory->{"getCustomInt{$i}Name"}();
            }
            if ($inventory->{"getCustomBool{$i}State"}()) {
                $headers[] = $inventory->{"getCustomBool{$i}Name"}();
            }
            if ($inventory->{"getCustomLink{$i}State"}()) {
                $headers[] = $inventory->{"getCustomLink{$i}Name"}();
            }
        }

        // Создаем CSV
        $output = fopen('php://temp', 'r+');

        // Записываем заголовки (стандартный CSV с запятыми)
        fputcsv($output, $headers);

        // Записываем данные
        foreach ($items as $item) {
            $row = [
                $item->getCustomId(),
                $item->getCreatedAt()->format('Y-m-d H:i:s'),
                $item->getUpdatedAt()?->format('Y-m-d H:i:s'),
                $item->getCreatedBy()->getUsername() ?: $item->getCreatedBy()->getEmail(),
            ];

            // Добавляем значения кастомных полей
            for ($i = 1; $i <= 3; $i++) {
                if ($inventory->{"getCustomString{$i}State"}()) {
                    $row[] = $item->{"getCustomString{$i}Value"}() ?? '';
                }
                if ($inventory->{"getCustomText{$i}State"}()) {
                    $row[] = $item->{"getCustomText{$i}Value"}() ?? '';
                }
                if ($inventory->{"getCustomInt{$i}State"}()) {
                    $row[] = $item->{"getCustomInt{$i}Value"}() ?? '';
                }
                if ($inventory->{"getCustomBool{$i}State"}()) {
                    $value = $item->{"getCustomBool{$i}Value"}();
                    $row[] = $value ? $this->translator->trans('yes') : $this->translator->trans('no');
                }
                if ($inventory->{"getCustomLink{$i}State"}()) {
                    $row[] = $item->{"getCustomLink{$i}Value"}() ?? '';
                }
            }

            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    public function exportItemsToExcel(Inventory $inventory): string
    {
        // Получаем все элементы инвентаря без пагинации
        $items = $this->itemRepository->findBy(['inventory' => $inventory], ['customId' => 'ASC']);

        // Создаем XML для Excel (SpreadsheetML format)
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= ' <Worksheet ss:Name="' . htmlspecialchars($inventory->getTitle()) . '">' . "\n";
        $xml .= '  <Table>' . "\n";

        // Заголовки
        $xml .= '   <Row>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($this->translator->trans('export.custom_id')) . '</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($this->translator->trans('export.created_at')) . '</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($this->translator->trans('export.updated_at')) . '</Data></Cell>' . "\n";
        $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($this->translator->trans('export.created_by')) . '</Data></Cell>' . "\n";

        // Добавляем заголовки для кастомных полей
        for ($i = 1; $i <= 3; $i++) {
            if ($inventory->{"getCustomString{$i}State"}()) {
                $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($inventory->{"getCustomString{$i}Name"}()) . '</Data></Cell>' . "\n";
            }
            if ($inventory->{"getCustomText{$i}State"}()) {
                $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($inventory->{"getCustomText{$i}Name"}()) . '</Data></Cell>' . "\n";
            }
            if ($inventory->{"getCustomInt{$i}State"}()) {
                $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($inventory->{"getCustomInt{$i}Name"}()) . '</Data></Cell>' . "\n";
            }
            if ($inventory->{"getCustomBool{$i}State"}()) {
                $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($inventory->{"getCustomBool{$i}Name"}()) . '</Data></Cell>' . "\n";
            }
            if ($inventory->{"getCustomLink{$i}State"}()) {
                $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($inventory->{"getCustomLink{$i}Name"}()) . '</Data></Cell>' . "\n";
            }
        }
        $xml .= '   </Row>' . "\n";

        // Данные
        foreach ($items as $item) {
            $xml .= '   <Row>' . "\n";
            $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($item->getCustomId()) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($item->getCreatedAt()->format('Y-m-d H:i:s')) . '</Data></Cell>' . "\n";
            $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($item->getUpdatedAt()?->format('Y-m-d H:i:s') ?: '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($item->getCreatedBy()->getUsername() ?: $item->getCreatedBy()->getEmail()) . '</Data></Cell>' . "\n";

            // Добавляем значения кастомных полей
            for ($i = 1; $i <= 3; $i++) {
                if ($inventory->{"getCustomString{$i}State"}()) {
                    $value = $item->{"getCustomString{$i}Value"}() ?? '';
                    $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($value) . '</Data></Cell>' . "\n";
                }
                if ($inventory->{"getCustomText{$i}State"}()) {
                    $value = $item->{"getCustomText{$i}Value"}() ?? '';
                    $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($value) . '</Data></Cell>' . "\n";
                }
                if ($inventory->{"getCustomInt{$i}State"}()) {
                    $value = $item->{"getCustomInt{$i}Value"}() ?? '';
                    $xml .= '    <Cell><Data ss:Type="Number">' . htmlspecialchars($value) . '</Data></Cell>' . "\n";
                }
                if ($inventory->{"getCustomBool{$i}State"}()) {
                    $value = $item->{"getCustomBool{$i}Value"}();
                    $displayValue = $value ? $this->translator->trans('yes') : $this->translator->trans('no');
                    $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($displayValue) . '</Data></Cell>' . "\n";
                }
                if ($inventory->{"getCustomLink{$i}State"}()) {
                    $value = $item->{"getCustomLink{$i}Value"}() ?? '';
                    $xml .= '    <Cell><Data ss:Type="String">' . htmlspecialchars($value) . '</Data></Cell>' . "\n";
                }
            }
            $xml .= '   </Row>' . "\n";
        }

        $xml .= '  </Table>' . "\n";
        $xml .= ' </Worksheet>' . "\n";
        $xml .= '</Workbook>' . "\n";

        return $xml;
    }
}
