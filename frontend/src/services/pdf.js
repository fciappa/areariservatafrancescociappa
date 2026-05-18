import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

function fmt(v) {
  return '€ ' + Number(v ?? 0).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d) {
  return new Date(d).toLocaleDateString('it-IT');
}

/**
 * Generate a PDF for a client invoice.
 * @param {Object} inv - invoice object from API (with .items array)
 */
export function exportInvoicePdf(inv) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
  const pageW = doc.internal.pageSize.getWidth();
  let y = 20;

  // Header
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(18);
  doc.setTextColor(15, 52, 96);
  doc.text('FATTURA', 14, y);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(11);
  doc.setTextColor(55, 65, 81);
  doc.text(inv.invoice_number, pageW - 14, y, { align: 'right' });

  y += 7;
  doc.setFontSize(9);
  doc.setTextColor(107, 114, 128);
  doc.text(`Data: ${fmtDate(inv.invoice_date)}`, pageW - 14, y, { align: 'right' });

  y += 12;

  // Client info
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10);
  doc.setTextColor(55, 65, 81);
  doc.text('Cliente', 14, y);
  y += 5;
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(10);
  doc.text(inv.company_name ?? '', 14, y);
  y += 5;

  // Divider
  y += 5;
  doc.setDrawColor(229, 231, 235);
  doc.line(14, y, pageW - 14, y);
  y += 8;

  // Items table
  const rows = (inv.items ?? []).map(item => [
    item.description,
    `${item.hours}h`,
    fmt(item.hourly_rate),
    item.tax_inclusive ? 'Incl.' : 'Escl.',
    fmt(item.line_total),
  ]);

  autoTable(doc, {
    startY: y,
    head: [['Descrizione', 'Ore', '€/ora', '4%', 'Lordo']],
    body: rows,
    theme: 'grid',
    headStyles: { fillColor: [15, 52, 96], textColor: 255, fontSize: 9, fontStyle: 'bold' },
    bodyStyles: { fontSize: 9, textColor: [55, 65, 81] },
    columnStyles: {
      0: { cellWidth: 'auto' },
      1: { cellWidth: 20, halign: 'right' },
      2: { cellWidth: 28, halign: 'right' },
      3: { cellWidth: 18, halign: 'center' },
      4: { cellWidth: 32, halign: 'right' },
    },
    margin: { left: 14, right: 14 },
  });

  y = doc.lastAutoTable.finalY + 10;

  // Totals box (right-aligned)
  const boxW = 90;
  const boxX = pageW - 14 - boxW;
  const lineH = 7;

  doc.setFontSize(9);
  doc.setTextColor(107, 114, 128);
  doc.text('Imponibile', boxX, y);
  doc.setTextColor(55, 65, 81);
  doc.text(fmt(inv.subtotal), pageW - 14, y, { align: 'right' });
  y += lineH;

  doc.setTextColor(107, 114, 128);
  doc.text('4% ritenuta', boxX, y);
  doc.setTextColor(55, 65, 81);
  doc.text(fmt(inv.tax_amount), pageW - 14, y, { align: 'right' });
  y += lineH;

  doc.setTextColor(107, 114, 128);
  doc.text('Bollo virtuale', boxX, y);
  doc.setTextColor(55, 65, 81);
  doc.text(fmt(inv.stamp_duty), pageW - 14, y, { align: 'right' });
  y += lineH;

  // Total line
  doc.setDrawColor(229, 231, 235);
  doc.line(boxX, y, pageW - 14, y);
  y += 4;
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.setTextColor(5, 150, 105); // green
  doc.text('TOTALE', boxX, y);
  doc.text(fmt(inv.total), pageW - 14, y, { align: 'right' });

  // Notes
  if (inv.notes) {
    y += 14;
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(9);
    doc.setTextColor(107, 114, 128);
    doc.text(`Note: ${inv.notes}`, 14, y);
  }

  doc.save(`fattura-${inv.invoice_number.replace(/\//g, '-')}.pdf`);
}

/**
 * Generate a PDF for a collaborator proforma invoice.
 * @param {Object} inv - collab invoice object from API (with .items array)
 */
export function exportCollabInvoicePdf(inv) {
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
  const pageW = doc.internal.pageSize.getWidth();
  let y = 20;

  // Header
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(18);
  doc.setTextColor(15, 52, 96);
  doc.text('FATTURA PROFORMA', 14, y);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(11);
  doc.setTextColor(55, 65, 81);
  doc.text(inv.invoice_number, pageW - 14, y, { align: 'right' });

  y += 7;
  doc.setFontSize(9);
  doc.setTextColor(107, 114, 128);
  doc.text(`Data: ${fmtDate(inv.invoice_date)}`, pageW - 14, y, { align: 'right' });

  y += 12;

  // Collaborator info
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10);
  doc.setTextColor(55, 65, 81);
  doc.text('Collaboratore', 14, y);
  y += 5;
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(10);
  const collabName = `${inv.first_name ?? ''} ${inv.last_name ?? ''}`.trim();
  doc.text(collabName, 14, y);
  y += 5;

  y += 5;
  doc.setDrawColor(229, 231, 235);
  doc.line(14, y, pageW - 14, y);
  y += 8;

  // Items table
  const rows = (inv.items ?? []).map(item => [
    item.description,
    `${item.hours}h`,
    fmt(item.hourly_rate),
    item.tax_inclusive ? 'Incl.' : 'Escl.',
    fmt(item.line_total),
  ]);

  autoTable(doc, {
    startY: y,
    head: [['Descrizione', 'Ore', '€/ora', '4%', 'Lordo']],
    body: rows,
    theme: 'grid',
    headStyles: { fillColor: [15, 52, 96], textColor: 255, fontSize: 9, fontStyle: 'bold' },
    bodyStyles: { fontSize: 9, textColor: [55, 65, 81] },
    columnStyles: {
      0: { cellWidth: 'auto' },
      1: { cellWidth: 20, halign: 'right' },
      2: { cellWidth: 28, halign: 'right' },
      3: { cellWidth: 18, halign: 'center' },
      4: { cellWidth: 32, halign: 'right' },
    },
    margin: { left: 14, right: 14 },
  });

  y = doc.lastAutoTable.finalY + 10;

  // Totals
  const boxW = 90;
  const boxX = pageW - 14 - boxW;
  const lineH = 7;

  doc.setFontSize(9);
  doc.setTextColor(107, 114, 128);
  doc.text('Imponibile', boxX, y);
  doc.setTextColor(55, 65, 81);
  doc.text(fmt(inv.subtotal), pageW - 14, y, { align: 'right' });
  y += lineH;

  doc.setTextColor(107, 114, 128);
  doc.text('4% ritenuta', boxX, y);
  doc.setTextColor(55, 65, 81);
  doc.text(fmt(inv.tax_amount), pageW - 14, y, { align: 'right' });
  y += lineH;

  doc.setDrawColor(229, 231, 235);
  doc.line(boxX, y, pageW - 14, y);
  y += 4;
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(11);
  doc.setTextColor(5, 150, 105);
  doc.text('TOTALE', boxX, y);
  doc.text(fmt(inv.total), pageW - 14, y, { align: 'right' });

  if (inv.notes) {
    y += 14;
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(9);
    doc.setTextColor(107, 114, 128);
    doc.text(`Note: ${inv.notes}`, 14, y);
  }

  doc.save(`proforma-${inv.invoice_number.replace(/\//g, '-')}.pdf`);
}
