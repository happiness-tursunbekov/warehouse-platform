<?php

namespace App\Console\Commands;

use App\Services\BigCommerceService;
use App\Services\Cin7Service;
use App\Services\ConnectWiseService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class FixUsedCables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-used-cables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(Cin7Service $cin7Service, ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $stock = '
        [
  {
    "ID": "0f558b11-aa4f-49bf-bb83-51d7991bb459",
    "Bin": null,
    "SKU": "0023630(124ft)",
    "Name": "18 AWG 2 twisted Conductor Bare Copper, Non-Shielded Plenum",
    "Batch": null,
    "OnHand": 13,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 13,
    "ExpiryDate": null,
    "StockOnHand": 101.556
  },
  {
    "ID": "0f558b11-aa4f-49bf-bb83-51d7991bb459",
    "Bin": null,
    "SKU": "0023630(124ft)",
    "Name": "18 AWG 2 twisted Conductor Bare Copper, Non-Shielded Plenum",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "0f558b11-aa4f-49bf-bb83-51d7991bb459",
    "Bin": null,
    "SKU": "0023630(124ft)",
    "Name": "18 AWG 2 twisted Conductor Bare Copper, Non-Shielded Plenum",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "5e0997de-c26a-4a2e-bb48-eaefb2afeb05",
    "Bin": null,
    "SKU": "0023830(106ft)",
    "Name": "18-04 UNS STR CMP Ylw Jkt",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "5e0997de-c26a-4a2e-bb48-eaefb2afeb05",
    "Bin": null,
    "SKU": "0023830(106ft)",
    "Name": "18-04 UNS STR CMP Ylw Jkt",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "5e0997de-c26a-4a2e-bb48-eaefb2afeb05",
    "Bin": null,
    "SKU": "0023830(106ft)",
    "Name": "18-04 UNS STR CMP Ylw Jkt",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "ec05505c-e2b8-4ec3-a018-912f6ea9563d",
    "Bin": null,
    "SKU": "12101-701",
    "Name": "Cable Runway Radius Drop Stringer",
    "Batch": null,
    "OnHand": 13,
    "Barcode": "703957170658",
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 13,
    "ExpiryDate": null,
    "StockOnHand": 649.87
  },
  {
    "ID": "ec05505c-e2b8-4ec3-a018-912f6ea9563d",
    "Bin": null,
    "SKU": "12101-701",
    "Name": "Cable Runway Radius Drop Stringer",
    "Batch": null,
    "OnHand": 0,
    "Barcode": "703957170658",
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "ec05505c-e2b8-4ec3-a018-912f6ea9563d",
    "Bin": null,
    "SKU": "12101-701",
    "Name": "Cable Runway Radius Drop Stringer",
    "Batch": null,
    "OnHand": 0,
    "Barcode": "703957170658",
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "9ed6350f-d259-47e9-9860-409cb898d9d9",
    "Bin": null,
    "SKU": "129454",
    "Name": "Monoprice Cat6A 6in Blue 10-Pk Patch Cable",
    "Batch": null,
    "OnHand": 17,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 17,
    "ExpiryDate": null,
    "StockOnHand": 297.126
  },
  {
    "ID": "9ed6350f-d259-47e9-9860-409cb898d9d9",
    "Bin": null,
    "SKU": "129454",
    "Name": "Monoprice Cat6A 6in Blue 10-Pk Patch Cable",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "9ed6350f-d259-47e9-9860-409cb898d9d9",
    "Bin": null,
    "SKU": "129454",
    "Name": "Monoprice Cat6A 6in Blue 10-Pk Patch Cable",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "f0a53d8b-d1cc-4327-bd5f-68fefed0cbb5",
    "Bin": null,
    "SKU": "2X2VG-HDTR61",
    "Name": "Amplified Speaker In-Ceiling Set",
    "Batch": null,
    "OnHand": 2,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 2,
    "ExpiryDate": null,
    "StockOnHand": 1275.98
  },
  {
    "ID": "f0a53d8b-d1cc-4327-bd5f-68fefed0cbb5",
    "Bin": null,
    "SKU": "2X2VG-HDTR61",
    "Name": "Amplified Speaker In-Ceiling Set",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "f0a53d8b-d1cc-4327-bd5f-68fefed0cbb5",
    "Bin": null,
    "SKU": "2X2VG-HDTR61",
    "Name": "Amplified Speaker In-Ceiling Set",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "cb73096b-f486-43df-8ff0-07fefec7d49b",
    "Bin": null,
    "SKU": "2X2VG-HDTR62",
    "Name": "Amplified In-Ceiling Speaker Set 2Pcs",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "cb73096b-f486-43df-8ff0-07fefec7d49b",
    "Bin": null,
    "SKU": "2X2VG-HDTR62",
    "Name": "Amplified In-Ceiling Speaker Set 2Pcs",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "cb73096b-f486-43df-8ff0-07fefec7d49b",
    "Bin": null,
    "SKU": "2X2VG-HDTR62",
    "Name": "Amplified In-Ceiling Speaker Set 2Pcs",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "e50b37a5-5bef-4f5a-b1c2-3adfceb2f279",
    "Bin": null,
    "SKU": "3312617902",
    "Name": "ROHS COMPLIANT RAIL STEEL 3312617902",
    "Batch": null,
    "OnHand": 22,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 22,
    "ExpiryDate": null,
    "StockOnHand": 752.4
  },
  {
    "ID": "e50b37a5-5bef-4f5a-b1c2-3adfceb2f279",
    "Bin": null,
    "SKU": "3312617902",
    "Name": "ROHS COMPLIANT RAIL STEEL 3312617902",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "e50b37a5-5bef-4f5a-b1c2-3adfceb2f279",
    "Bin": null,
    "SKU": "3312617902",
    "Name": "ROHS COMPLIANT RAIL STEEL 3312617902",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "4ebd9dc8-12cc-4e0c-9fd6-d02d10ee62cc",
    "Bin": null,
    "SKU": "6P4P24-BL-P-BER-AP-NS",
    "Name": "LANmark-6 Cat 6 Plenum 4-Pair UTP Cable, Blue, 1000 ft.",
    "Batch": null,
    "OnHand": 2,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 2,
    "ExpiryDate": null,
    "StockOnHand": 447.462
  },
  {
    "ID": "4ebd9dc8-12cc-4e0c-9fd6-d02d10ee62cc",
    "Bin": null,
    "SKU": "6P4P24-BL-P-BER-AP-NS",
    "Name": "LANmark-6 Cat 6 Plenum 4-Pair UTP Cable, Blue, 1000 ft.",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "4ebd9dc8-12cc-4e0c-9fd6-d02d10ee62cc",
    "Bin": null,
    "SKU": "6P4P24-BL-P-BER-AP-NS",
    "Name": "LANmark-6 Cat 6 Plenum 4-Pair UTP Cable, Blue, 1000 ft.",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "ab83ceb0-8313-4a11-81dc-6061c21ba0e2",
    "Bin": null,
    "SKU": "B0072JVT02",
    "Name": "Cable Matters Rackmount or Wall Mount 1U 24 Port Patch panel",
    "Batch": null,
    "OnHand": 7,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 7,
    "ExpiryDate": null,
    "StockOnHand": 190.89
  },
  {
    "ID": "ab83ceb0-8313-4a11-81dc-6061c21ba0e2",
    "Bin": null,
    "SKU": "B0072JVT02",
    "Name": "Cable Matters Rackmount or Wall Mount 1U 24 Port Patch panel",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "ab83ceb0-8313-4a11-81dc-6061c21ba0e2",
    "Bin": null,
    "SKU": "B0072JVT02",
    "Name": "Cable Matters Rackmount or Wall Mount 1U 24 Port Patch panel",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "567efd18-84dd-4c5b-b48e-360cd0fb8179",
    "Bin": null,
    "SKU": "B08HZJ627G",
    "Name": "NavePoint Keystone Jack Wall Plate 2-Port, 10 pack",
    "Batch": null,
    "OnHand": 15,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 15,
    "ExpiryDate": null,
    "StockOnHand": 176.985
  },
  {
    "ID": "567efd18-84dd-4c5b-b48e-360cd0fb8179",
    "Bin": null,
    "SKU": "B08HZJ627G",
    "Name": "NavePoint Keystone Jack Wall Plate 2-Port, 10 pack",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "567efd18-84dd-4c5b-b48e-360cd0fb8179",
    "Bin": null,
    "SKU": "B08HZJ627G",
    "Name": "NavePoint Keystone Jack Wall Plate 2-Port, 10 pack",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "ab4c23d2-a7b0-4018-9575-85f2084ba967",
    "Bin": null,
    "SKU": "B08HZJ627G-RF",
    "Name": "NavePoint Keystone Jack Wall Plate 2-Port, 10 pack",
    "Batch": null,
    "OnHand": 3,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 3,
    "ExpiryDate": null,
    "StockOnHand": 1.188
  },
  {
    "ID": "ab4c23d2-a7b0-4018-9575-85f2084ba967",
    "Bin": null,
    "SKU": "B08HZJ627G-RF",
    "Name": "NavePoint Keystone Jack Wall Plate 2-Port, 10 pack",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "ab4c23d2-a7b0-4018-9575-85f2084ba967",
    "Bin": null,
    "SKU": "B08HZJ627G-RF",
    "Name": "NavePoint Keystone Jack Wall Plate 2-Port, 10 pack",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "7912b2a2-949e-4789-8f10-df02505f98c9",
    "Bin": null,
    "SKU": "D-CIJ3",
    "Name": "Radio Design Labs D-CIJ3 Input Jacks",
    "Batch": null,
    "OnHand": 1,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 50 00 Distributed Communications (PA)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 1,
    "ExpiryDate": null,
    "StockOnHand": 106.15
  },
  {
    "ID": "7912b2a2-949e-4789-8f10-df02505f98c9",
    "Bin": null,
    "SKU": "D-CIJ3",
    "Name": "Radio Design Labs D-CIJ3 Input Jacks",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 50 00 Distributed Communications (PA)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "7912b2a2-949e-4789-8f10-df02505f98c9",
    "Bin": null,
    "SKU": "D-CIJ3",
    "Name": "Radio Design Labs D-CIJ3 Input Jacks",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 50 00 Distributed Communications (PA)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "17123856-54f6-4923-960c-76ddca771265",
    "Bin": null,
    "SKU": "EMT400",
    "Name": "Arlington EMT400 Non-Metallic Bushing, 4 Inch Push-On Insula",
    "Batch": null,
    "OnHand": 65,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 65,
    "ExpiryDate": null,
    "StockOnHand": 377.2548
  },
  {
    "ID": "17123856-54f6-4923-960c-76ddca771265",
    "Bin": null,
    "SKU": "EMT400",
    "Name": "Arlington EMT400 Non-Metallic Bushing, 4 Inch Push-On Insula",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "17123856-54f6-4923-960c-76ddca771265",
    "Bin": null,
    "SKU": "EMT400",
    "Name": "Arlington EMT400 Non-Metallic Bushing, 4 Inch Push-On Insula",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "fa0c8d67-1f6d-425c-ad94-00dc26bbb4d6",
    "Bin": null,
    "SKU": "LTB762",
    "Name": "Wall Mount Bracket for 5 inch PTZ",
    "Batch": null,
    "OnHand": 6,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 20 00 Video Surveillance (CCTV)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 6,
    "ExpiryDate": null,
    "StockOnHand": 269.46
  },
  {
    "ID": "fa0c8d67-1f6d-425c-ad94-00dc26bbb4d6",
    "Bin": null,
    "SKU": "LTB762",
    "Name": "Wall Mount Bracket for 5 inch PTZ",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 20 00 Video Surveillance (CCTV)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "fa0c8d67-1f6d-425c-ad94-00dc26bbb4d6",
    "Bin": null,
    "SKU": "LTB762",
    "Name": "Wall Mount Bracket for 5 inch PTZ",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 20 00 Video Surveillance (CCTV)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "7c264e54-e976-4001-b7a3-2a9000349aea",
    "Bin": null,
    "SKU": "M-46-FW",
    "Name": "4MP Mini Dome Indoor/Outdoor Fixed 2.8MM Lens with",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 20 00 Video Surveillance (CCTV)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "7c264e54-e976-4001-b7a3-2a9000349aea",
    "Bin": null,
    "SKU": "M-46-FW",
    "Name": "4MP Mini Dome Indoor/Outdoor Fixed 2.8MM Lens with",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 20 00 Video Surveillance (CCTV)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "7c264e54-e976-4001-b7a3-2a9000349aea",
    "Bin": null,
    "SKU": "M-46-FW",
    "Name": "4MP Mini Dome Indoor/Outdoor Fixed 2.8MM Lens with",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 20 00 Video Surveillance (CCTV)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "34e422f0-e2fa-4bc9-b546-3283a8427d36",
    "Bin": null,
    "SKU": "NYC-633",
    "Name": "Cat6a Plenum Pure Copper UTP 1000ft 750MHz 23 AWG Cable-Yellow",
    "Batch": null,
    "OnHand": 7,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 7,
    "ExpiryDate": null,
    "StockOnHand": 1890
  },
  {
    "ID": "34e422f0-e2fa-4bc9-b546-3283a8427d36",
    "Bin": null,
    "SKU": "NYC-633",
    "Name": "Cat6a Plenum Pure Copper UTP 1000ft 750MHz 23 AWG Cable-Yellow",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "34e422f0-e2fa-4bc9-b546-3283a8427d36",
    "Bin": null,
    "SKU": "NYC-633",
    "Name": "Cat6a Plenum Pure Copper UTP 1000ft 750MHz 23 AWG Cable-Yellow",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "6866417a-d13a-425f-93d2-35644f6be964",
    "Bin": null,
    "SKU": "SFP-10G-SR-S",
    "Name": "Cisco - SFP+ transceiver module - 10GbE",
    "Batch": null,
    "OnHand": 8,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 8,
    "ExpiryDate": null,
    "StockOnHand": 124.2
  },
  {
    "ID": "6866417a-d13a-425f-93d2-35644f6be964",
    "Bin": null,
    "SKU": "SFP-10G-SR-S",
    "Name": "Cisco - SFP+ transceiver module - 10GbE",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "6866417a-d13a-425f-93d2-35644f6be964",
    "Bin": null,
    "SKU": "SFP-10G-SR-S",
    "Name": "Cisco - SFP+ transceiver module - 10GbE",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "9c28b4b9-2908-4724-ba85-e60e82097cd4",
    "Bin": null,
    "SKU": "SMART1500LCD",
    "Name": "Tripp Lite by Eaton UPS Smart LCD 1500VA 900W 120V",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 20 00 Data Network and Wireless",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "9c28b4b9-2908-4724-ba85-e60e82097cd4",
    "Bin": null,
    "SKU": "SMART1500LCD",
    "Name": "Tripp Lite by Eaton UPS Smart LCD 1500VA 900W 120V",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 20 00 Data Network and Wireless",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "9c28b4b9-2908-4724-ba85-e60e82097cd4",
    "Bin": null,
    "SKU": "SMART1500LCD",
    "Name": "Tripp Lite by Eaton UPS Smart LCD 1500VA 900W 120V",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 20 00 Data Network and Wireless",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "a7f7c345-88be-4d45-a12c-3077c309b2fa",
    "Bin": null,
    "SKU": "V-1246",
    "Name": "18/4 Plenum Speaker Wire",
    "Batch": null,
    "OnHand": 1,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 1,
    "ExpiryDate": null,
    "StockOnHand": 197.991
  },
  {
    "ID": "a7f7c345-88be-4d45-a12c-3077c309b2fa",
    "Bin": null,
    "SKU": "V-1246",
    "Name": "18/4 Plenum Speaker Wire",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "a7f7c345-88be-4d45-a12c-3077c309b2fa",
    "Bin": null,
    "SKU": "V-1246",
    "Name": "18/4 Plenum Speaker Wire",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "2a0e1820-933a-47b3-9e9d-e1be86026ebd",
    "Bin": null,
    "SKU": "V12H804001",
    "Name": "Epson SpeedConnect Above Tile Suspended Ceiling Kit",
    "Batch": null,
    "OnHand": 2,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 2,
    "ExpiryDate": null,
    "StockOnHand": 210.24
  },
  {
    "ID": "2a0e1820-933a-47b3-9e9d-e1be86026ebd",
    "Bin": null,
    "SKU": "V12H804001",
    "Name": "Epson SpeedConnect Above Tile Suspended Ceiling Kit",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "2a0e1820-933a-47b3-9e9d-e1be86026ebd",
    "Bin": null,
    "SKU": "V12H804001",
    "Name": "Epson SpeedConnect Above Tile Suspended Ceiling Kit",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 40 00 Audio Visual (AV Systems)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "e41bb89c-790e-40a6-bc4d-8d6468825422",
    "Bin": null,
    "SKU": "V-9022A-2",
    "Name": "Valcom - 2x2 lay in ceiling speaker  V-9022A-2",
    "Batch": null,
    "OnHand": 11,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 50 00 Distributed Communications (PA)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 11,
    "ExpiryDate": null,
    "StockOnHand": 1005.6321
  },
  {
    "ID": "e41bb89c-790e-40a6-bc4d-8d6468825422",
    "Bin": null,
    "SKU": "V-9022A-2",
    "Name": "Valcom - 2x2 lay in ceiling speaker  V-9022A-2",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 50 00 Distributed Communications (PA)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "e41bb89c-790e-40a6-bc4d-8d6468825422",
    "Bin": null,
    "SKU": "V-9022A-2",
    "Name": "Valcom - 2x2 lay in ceiling speaker  V-9022A-2",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 50 00 Distributed Communications (PA)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "c637aec2-c807-49bc-a8e3-08c02463c940",
    "Bin": null,
    "SKU": "VIP78",
    "Name": "Tamper Switch for Access Control Cabinets",
    "Batch": null,
    "OnHand": 10,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 10,
    "ExpiryDate": null,
    "StockOnHand": 34.83
  },
  {
    "ID": "c637aec2-c807-49bc-a8e3-08c02463c940",
    "Bin": null,
    "SKU": "VIP78",
    "Name": "Tamper Switch for Access Control Cabinets",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "c637aec2-c807-49bc-a8e3-08c02463c940",
    "Bin": null,
    "SKU": "VIP78",
    "Name": "Tamper Switch for Access Control Cabinets",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "026afd00-4363-4888-972b-6c755be72d16",
    "Bin": null,
    "SKU": "Wensilon 8x1-1/2",
    "Name": "#8×1-1/2” for Sheet Metal Self-Tapping Screws 410 Black Stai",
    "Batch": null,
    "OnHand": 600,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 600,
    "ExpiryDate": null,
    "StockOnHand": 16.2
  },
  {
    "ID": "026afd00-4363-4888-972b-6c755be72d16",
    "Bin": null,
    "SKU": "Wensilon 8x1-1/2",
    "Name": "#8×1-1/2” for Sheet Metal Self-Tapping Screws 410 Black Stai",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "026afd00-4363-4888-972b-6c755be72d16",
    "Bin": null,
    "SKU": "Wensilon 8x1-1/2",
    "Name": "#8×1-1/2” for Sheet Metal Self-Tapping Screws 410 Black Stai",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "28 10 00 Access Control (ACS)",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "52f54905-f103-4efc-b13c-daf16358e6ef",
    "Bin": null,
    "SKU": "X003Y8Y5ST",
    "Name": "Blulu 25 Pcs J Hooks Cable Support J Hook Hangers with Swive",
    "Batch": null,
    "OnHand": 3,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Azad May Inventory",
    "Allocated": 0,
    "Available": 3,
    "ExpiryDate": null,
    "StockOnHand": 153.9
  },
  {
    "ID": "52f54905-f103-4efc-b13c-daf16358e6ef",
    "Bin": null,
    "SKU": "X003Y8Y5ST",
    "Name": "Blulu 25 Pcs J Hooks Cable Support J Hook Hangers with Swive",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Binyod Inventory",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  },
  {
    "ID": "52f54905-f103-4efc-b13c-daf16358e6ef",
    "Bin": null,
    "SKU": "X003Y8Y5ST",
    "Name": "Blulu 25 Pcs J Hooks Cable Support J Hook Hangers with Swive",
    "Batch": null,
    "OnHand": 0,
    "Barcode": null,
    "MaxRows": 72,
    "OnOrder": 0,
    "Category": "27 10 00 Structured Cabling",
    "Location": "Vendor location",
    "Allocated": 0,
    "Available": 0,
    "ExpiryDate": null,
    "StockOnHand": 0
  }
]
        ';

        $lines = collect(json_decode($stock))->unique('ID');

        dd($lines);


//        $catalogItemIdentifiers = [
//            'Wensilon 8x1-1/2',
//            '2X2VG-HDTR61',
//            '2X2VG-HDTR62',
//            'V12H804001',
//            'SMS2B',
//            'SMART1500LCD',
//            'NYC-633',
//            '0023630',
//            '0023830',
//            '12101-701',
//            'B08HZJ627G',
//            'B08HZJ627G-RF',
//            'EMT400',
//            'M-46-v',
//            'M-46-FW',
//            'V-9022A-2',
//            'VIP78',
//            'B0072JVT02',
//            'SFP-10G-SR-S',
//            '12101-701',
//            '3312617902',
//            'D-CIJ3',
//            'LTB762',
//            'V-1246',
//            '6P4P24-BL-P-BER-AP-NS',
//            '129454',
//            'X003Y8Y5ST'
//        ];
//
//        $lines = collect($connectWiseService->getCatalogItems(1, 'identifier in ("' . implode('","', $catalogItemIdentifiers) . '")', pageSize: 1000))
//            ->map(function ($catalogItem) use ($cin7Service, $connectWiseService) {
//
//                $cin7Product = $cin7Service->productBySku($catalogItem->identifier);
//
//                $onHand = $connectWiseService->getCatalogItemOnHand($catalogItem->id)->count;
//
//                return [
//                    "ProductID" => $cin7Product->ID,
//                    "Quantity" => $onHand,
//                    "UnitCost" => $catalogItem->cost * 0.9,
//                    "Location" => Cin7Service::INVENTORY_AZAD_MAY
//                ];
//            })->toArray();
//
//        $cin7Service->stockAdjustBulk($lines);

//        $stock = $cin7Service->getStockAdjustment('e73df7c8-a050-4669-b3c5-c8fb0bfa7d19');
//
//        $stock->Status = 'COMPLETED';
//        $stock->Lines = $stock->NewStockLines;
//
//        unset($stock->NewStockLines);
//
//        $cin7Service->updateStockAdjustment($stock);


//        $mergedQtyArr1 = cache()->get('mergedQtyArr1');
//
//        dd($mergedQtyArr1->where('ProductID', 'ec05505c-e2b8-4ec3-a018-912f6ea9563d'));

//        $mergedQtyArr1 = cache()->get('mergedQtyArr1');

//        $mergedQtyArr2 = $mergedQtyArr2->map(function ($line) use ($cin7Service) {
//
//            $cin7Product = $cin7Service->productBySku($line['SKU']);
//            sleep(1);
//            if ($cin7Product) {
//                $line['ProductID'] = $cin7Product->ID;
//                unset($line['SKU']);
//            }
//
//            return $line;
//        });
//
//        cache()->put('mergedQtyArr2', $mergedQtyArr2);

//        dd($mergedQtyArr1);
//
//        $cin7Service->stockAdjustBulk($mergedQtyArr2->whereNotNull('ProductID')->values());



//        $mergedQtyArr1 = collect();
//        $mergedQtyArr2 = collect();
//
//        cache()->get('cin7adjustment')->where('SKU', '!=', 'CP-8845-K9=.')->where('UnitCost', '>', 0)->map(function ($line) use ($cin7Service, &$mergedQtyArr1, &$mergedQtyArr2) {
//            $stock = $cin7Service->productAvailabilityBySku($line['SKU']);
//
//            sleep(1);
//
//            if ($stock && $stock->OnHand > 0) {
//                $mergedQtyArr1->push($line);
//            } else {
//                $mergedQtyArr2->push($line);
//            }
//        });
//
//        cache()->put('mergedQtyArr1', $mergedQtyArr1);
//        cache()->put('mergedQtyArr2', $mergedQtyArr2);

//        $cin7Service->stockAdjustBulk($cin7adjustment->where('UnitCost', '>', 0)->filter(fn($line) => $line)->values());

//        collect($connectWiseService->getProductCatalogOnHand(1, 'onHand > 0', pageSize: 1000))->map(function ($onHand) use ($connectWiseService, &$cin7adjustment) {
//
//            $catalogItem = $connectWiseService->getCatalogItem($onHand->catalogItem->id);
//
//            $cost = $catalogItem->cost * 0.9;
//
//            $cin7adjustment->push([
//                "SKU" => $catalogItem->identifier,
//                "Quantity" => $onHand->onHand,
//                "UnitCost" => $cost,
//                "Location" => Cin7Service::INVENTORY_AZAD_MAY
//            ]);
//        });
//
//        cache()->put('cin7adjustment', $cin7adjustment);


    }
}

//$ship = $connectWiseService->getProductPickingShippingDetails(13890)[0];
//
//$ship->pickedQuantity = 0;
//$ship->shippedQuantity = 0;
//
//dd($connectWiseService->addOrUpdatePickShip($ship));

//        $mergedQtyArr = cache()->get('mergedQtyArr');
//        $mergedQtyArr1 = cache()->get('mergedQtyArr1');
//        $mergedQtyArr2 = cache()->get('mergedQtyArr2')->where('ProductID', '!=', 'bad80f11-b3eb-4f55-9e4c-4e0ce88b8cdd')->where('UnitCost' , '>', 0) ?: collect();
//
//        $cin7Service->stockAdjustBulk($mergedQtyArr2->values());

//$page = 1;
//
//while (true) {
//    $products = collect($connectWiseService->getProducts($page, 'cancelledFlag=false', 1000));
//
//    $products->map(function ($product) use ($connectWiseService) {
//        $pickingShippingDetails = collect($connectWiseService->getProductPickingShippingDetails($product->id, 1, 'lineNumber!=0'));
//
//        $picked = $pickingShippingDetails->pluck('pickedQuantity')->sum();
//        $shipped = $pickingShippingDetails->pluck('shippedQuantity')->sum();
//
//        if ($picked != $shipped) {
//
//            $connectWiseService->shipProduct($product->id, $picked-$shipped);
//
//            echo "{$product->id}:{$picked}:{$shipped}\n";
//        }
//    });
//
//    if ($products->count() < 1000) {
//        break;
//    }
//
//    $page++;
//}

//collect($bigCommerceService->getProducts(1, 250)->data)->map(function ($product) use ($bigCommerceService) {
//    collect($bigCommerceService->getProductModifiers($product->id))
//        ->map(function ($modifier, $index) use ($product, $bigCommerceService) {
//            try {
//                $bigCommerceService->updateProductModifier($product->id, [
//                    'id' => $modifier->id,
//                    'sort_order' => $index,
//                    'shared_option_id' => $modifier->shared_option_id ?? null
//                ]);
//            } catch (\Exception $e) {
//                if (Str::contains($e->getMessage(), '429 Too')) {
//                    sleep(5);
//
//                    $bigCommerceService->updateProductModifier($product->id, [
//                        'id' => $modifier->id,
//                        'sort_order' => $index
//                    ]);
//                } else {
//                    throw $e;
//                }
//            }
//        });
//});

//$cwItem = $connectWiseService->getCatalogItems(1, "identifier='SF300-48PP-RF'")[0];
//
//dd($cin7Service->createProduct(
//    $cwItem->identifier,
//    $cwItem->description,
//    $cwItem->category->name,
//    $cwItem->unitOfMeasure->name,
//    $cwItem->customerDescription,
//    $cwItem->price,
//    null,
//    null
//)->Products[0]);

//        $f = $cin7Service->productFamilies()->ProductFamilies;
//        sleep(1);
//        collect($f)->map(function ($pf) use ($cin7Service) {
//            if (count($pf->Products) > 1) {
//                return false;
//            }
//
//            $p = $pf->Products[0];
//
//            $product = $cin7Service->product($p->ID)->Products[0];
//            sleep(1);
//
//            $newProduct = $cin7Service->cloneProduct($product, $product->SKU . "-test")->Products[0];
//            sleep(1);
//
//            $cin7Service->updateProductFamily([
//                'ID' => $pf->ID,
//                'Products' => [[
//                    'ID' => $newProduct->ID,
//                    'Option1' => 'Test'
//                ]]
//            ]);
//            sleep(1);
//        });

//$page = 1;
//while (true) {
//    $length = collect($connectWiseService->getCatalogItems($page, 'inactiveFlag=false', null, null,1000))
//        ->map(function ($item) use ($cin7Service, $connectWiseService) {
//
//            if ($item->id < 1964 || in_array($item->category->id, [31, 32, 29, 16, 34, 33, 13, 30, 28, 3])) {
//                return false;
//            }
//
//            $i = 0;
//            while (true) {
//                try {
//                    $productFamily = $cin7Service->createProductFamily(
//                        $item->identifier . '-PROJECT',
//                        Str::replace('	', '', Str::trim($item->description)) . ($i ? " [{$i}]" : ""),
//                        $item->category->name,
//                        $item->unitOfMeasure->name,
//                        Str::replace('	', '', Str::trim($item->customerDescription))
//                    );
//
//                    sleep(1);
//
//                    break;
//                } catch (GuzzleException $e) {
//                    if (Str::contains($e->getMessage(), "'Name' already exists")) {
//                        $i++;
//                        continue;
//                    } elseif (Str::contains($e->getMessage(), "was not found reference book") || Str::contains($e->getMessage(), "'SKU' already exists") || Str::contains($e->getMessage(), "Category not found") || Str::contains($e->getMessage(), "than 45 characters")) {
//                        return false;
//                    }
//
//                    echo $item->identifier . "\n";
//
//                    throw $e;
//                }
//            }
//
//            collect($connectWiseService->getAttachments('ProductSetup', $item->id))->map(function ($image, $index) use ($cin7Service, $productFamily, $connectWiseService) {
//                $cin7Service->uploadProductFamilyAttachment($productFamily->ID, $image->fileName, base64_encode($connectWiseService->downloadAttachment($image->id)->getFile()->getContent()), $index == 0);
//                sleep(1);
//            });
//
//            try {
//                $connectWiseService->updateCatalogItemCin7ProductFamilyId($item, $productFamily->ID);
//            } catch (\Exception $e) {
//                echo $item->identifier . ": {$productFamily->ID}\n";
//                return false;
//            }
//
//            echo $item->identifier . "\n";
//        })->count();
//
//    if ($length < 1000) {
//        break;
//    }
//
//    $page++;
//}

//collect($bigCommerceService->getProducts(1, 250)->data)->map(function ($product) use ($bigCommerceService) {
//    if (in_array($product->id, [4640, 4638, 4637]) || $product->id < 4624) {
//        return false;
//    }
//
//    $image_url = $bigCommerceService->getProductVariants($product->id)->data[0]->image_url;
//
//    if (!$image_url) {
//        return false;
//    }
//
//    $bigCommerceService->uploadProductImageUrl($product->id, $image_url);
//});

//        collect($bigCommerceService->getProducts(1, 250)->data)->map(function ($product) use ($bigCommerceService) {
//            $value = $bigCommerceService->getProductOptions($product->id)->data[0];
//
//            $value->option_values[0]->is_default = true;
//
//            $bigCommerceService->updateProductOptions($product->id, $value);
//        });

//$f = $cin7Service->productFamilies()->ProductFamilies;
//sleep(1);
//collect($f)->map(function ($pf) use ($cin7Service) {
//    if (count($pf->Products) > 1) {
//        return false;
//    }
//
//    $p = $pf->Products[0];
//
//    $product = $cin7Service->product($p->ID)->Products[0];
//    sleep(1);
//
//    $newProduct = $cin7Service->cloneProduct($product, $product->SKU . "-test")->Products[0];
//    sleep(1);
//
//    $cin7Service->updateProductFamily([
//        'ID' => $pf->ID,
//        'Products' => [[
//            'ID' => $newProduct->ID,
//            'Option1' => 'Test'
//        ]]
//    ]);
//    sleep(1);
//});


//$cacheProducts = collect(cache()->get('bc-products'));
//
//collect($bigCommerceService->getProducts(1, 250)->data)->map(function ($product) use ($bigCommerceService, $cacheProducts) {
//    $product->sku = Str::replace('~', '', $product->sku);
//    $cacheProduct = $cacheProducts->where('sku', $product->sku)->first();
//    if (!$cacheProduct) {
//        $cacheProduct = $cacheProducts->filter(function ($item) use ($product) {
//            return false !== stripos($item->sku, $product->sku);
//        })->first();
//        if (!$cacheProduct) {
//            $cacheProduct = $cacheProducts->filter(function ($item) use ($product) {
//                return false !== stripos($item->sku, 'D6UP');
//            })->first();
//        }
//    }
//
//    if ($cacheProduct) {
//        $bigCommerceService->setProductCategories($product->id, $cacheProduct->categories);
//    }
//});


//$f = $cin7Service->productFamilies()->ProductFamilies;
//
//sleep(1);
//
//collect($f)->map(function ($pf) use ($cin7Service) {
//    collect($pf->Products)->map(function ($p) use ($cin7Service, $pf) {
//        $product = $cin7Service->product($p->ID)->Products[0];
//        sleep(1);
//
//        $product->Category = $pf->Category;
//
//        $cin7Service->updateProduct($product);
//        sleep(1);
//    });
//});


//        $products = [];
//        $item = $bigCommerceService->getProduct(4157)->data;
//
//        $cwItem = $connectWiseService->getCatalogItems(1, "identifier='STXC6-CCA-WP'")[0];
//
//        $product = $cin7Service->createProduct(
//            $item->sku,
//            $item->name,
//            $cwItem->category->name,
//            $cwItem->unitOfMeasure->name,
//            $item->description,
//            $item->price,
//            $item->weight,
//            $item->upc ?: null
//        )->Products[0];
//
//        collect($bigCommerceService->getProductImages($item->id)->data)->map(function ($image) use ($product, $cin7Service) {
//            $ext = explode('.', $image->image_file);
//
//            $cin7Service->uploadProductAttachment(
//                $product->ID,
//                time() . $image->id . '.' . $ext[count($ext) - 1],
//                base64_encode(file_get_contents($image->url_zoom))
//            );
//        });

//        $cin7Service->stockAdjust([
//            [
//                "ProductID" => $product->ID,
//                "SKU" => $product->SKU,
//                "ProductName" => $product->Name,
//                "Quantity" => $item->inventory_level,
//                "UnitCost" => $item->price,
//                "Location" => "Azad May Inventory"
//            ]
//        ]);

//        $connectWiseService->updateCatalogItemCin7ProductId($cwItem, $product->ID);


//        $products[] = [
//            "ID" => $product->ID,
//            "SKU" => $product->SKU,
//            "Name" => $product->Name,
//            "Option1" => 'Azad May',
//            "Option2" => 'White'
//        ];
//
//        $item = $bigCommerceService->getProduct(1158)->data;
//
//        $cwItem = $connectWiseService->getCatalogItems(1, "identifier='STXC6-CCA-BP'")[0];
//
//        $product = $cin7Service->createProduct(
//            $item->sku,
//            $item->name,
//            $cwItem->category->name,
//            $cwItem->unitOfMeasure->name,
//            $item->description,
//            $item->price,
//            $item->weight,
//            $item->upc ?: null
//        )->Products[0];
//
//        collect($bigCommerceService->getProductImages($item->id)->data)->map(function ($image) use ($product, $cin7Service) {
//            $ext = explode('.', $image->image_file);
//
//            $cin7Service->uploadProductAttachment(
//                $product->ID,
//                time() . $image->id . '.' . $ext[count($ext) - 1],
//                base64_encode(file_get_contents($image->url_zoom))
//            );
//        });

//        $cin7Service->stockAdjust([
//            [
//                "ProductID" => $product->ID,
//                "SKU" => $product->SKU,
//                "ProductName" => $product->Name,
//                "Quantity" => $item->inventory_level,
//                "UnitCost" => $item->price,
//                "Location" => "Azad May Inventory"
//            ]
//        ]);

//        $connectWiseService->updateCatalogItemCin7ProductId($cwItem, $product->ID);
//
//        $products[] = [
//            "ID" => $product->ID,
//            "SKU" => $product->SKU,
//            "Name" => $product->Name,
//            "Option1" => 'Azad May',
//            "Option2" => 'Blue'
//        ];
//
//        $cin7Service->createProductFamily(
//            'STXC6-CCA',
//            $item->name,
//            $cwItem->category->name,
//            $cwItem->unitOfMeasure->name,
//            $item->description,
//            $item->price,
//            $products,
//            "Color"
//        );



//        $page = 1;
//        collect($connectWiseService->getCatalogItems($page, 'inactiveFlag=false and identifier="V-9022A-2"', null, null,1000))
//            ->map(function ($item) use ($cin7Service, $connectWiseService) {
//                $productFamily = $cin7Service->createProductFamily(
//                    $item->identifier,
//                    $item->description,
//                    $item->category->name,
//                    $item->unitOfMeasure->name,
//                    $item->customerDescription
//                );
//
//                $connectWiseService->updateCatalogItemCin7ProductFamilyId($item, $productFamily->ProductFamilies[0]->ID);
//            });

//        $adjustmentItems = collect();
//        $page = 1;
//        collect($bigCommerceService->getProducts($page, 250)->data)
//            ->map(function ($item) use ($cin7Service, $connectWiseService, $bigCommerceService, $adjustmentItems) {
//
//                $swIdentifier = Str::replace('*', '', Str::replace('STX', '', Str::replace('STX-', '', $item->sku)));
//
//                try {
//                    $cwItem = $connectWiseService->getCatalogItems(1, "identifier='{$swIdentifier}'")[0];
//                } catch (\Exception $e) {
//                    echo $item->sku . " - error\n";
//                    return false;
//                }
//
//                echo $item->sku . " - passed\n";
//
//                $variants = collect($bigCommerceService->getProductVariants($item->id, 1, 250)->data);
//
//                $products = $variants->map(function ($variant) use ($connectWiseService, $adjustmentItems, $item, $cwItem, $cin7Service, $bigCommerceService) {
//                    $name = $item->name;
//
//                    if (count($variant->option_values) > 0) {
//                        $name .= ', ' . $variant->option_values[0]->label;
//                    }
//
//                    $product = $cin7Service->createProduct(
//                        $variant->sku,
//                        $name,
//                        $cwItem->category->name,
//                        $cwItem->unitOfMeasure->name,
//                        $item->description,
//                        $variant->price,
//                        $variant->weight,
//                        $variant->upc ?: null
//                    )->Products[0];
//
//                    collect($bigCommerceService->getProductImages($item->id)->data)->map(function ($image) use ($product, $cin7Service) {
//                        $ext = explode('.', $image->image_file);
//
//                        $cin7Service->uploadProductAttachment(
//                            $product->ID,
//                            time() . $image->id . '.' . $ext[count($ext) - 1],
//                            base64_encode(file_get_contents($image->url_zoom))
//                        );
//                    });
//
//                    $adjustmentItems->push([
//                        "ProductID" => $product->ID,
//                        "SKU" => $product->SKU,
//                        "ProductName" => $product->Name,
//                        "Quantity" => $variant->inventory_level,
//                        "UnitCost" => $variant->price,
//                        "Location" => "Azad May Inventory"
//                    ]);
//
//                    cache()->put('adjustment-items', $adjustmentItems);
//
//                    $connectWiseService->updateCatalogItemCin7ProductId($cwItem, $product->ID);
//
//                    return [
//                        "ID" => $product->ID,
//                        "SKU" => $product->SKU,
//                        "Name" => $product->Name,
//                        "Option1" => 'Azad May',
//                        "Option2" => count($variant->option_values) > 0 ? $variant->option_values[0]->label : null
//                    ];
//                })->toArray();
//
//                $cin7Service->createProductFamily(
//                    $swIdentifier,
//                    $item->name,
//                    $cwItem->category->name,
//                    $cwItem->unitOfMeasure->name,
//                    $item->description,
//                    $item->price,
//                    $products,
//                    $variants->count() > 1 ? "Color" : null
//                );
//            });


//        /** @var Collection $adjustmentItems */
//        $adjustmentItems = cache()->get('adjustment-items');
//
//        $cin7Service->stockAdjust($adjustmentItems->toArray());
