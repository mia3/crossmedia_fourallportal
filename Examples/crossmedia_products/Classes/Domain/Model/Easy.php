<?php

namespace Crossmedia\Products\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Easy extends AbstractEntity
{
  protected string $description = '';
  protected string $availability = '';
  protected bool $launched = false;
  protected string $manufacturer = '';
  protected string $manufacturer_part_code = '';
  protected int $ex_vat_price = 0;
  protected int $inc_vat_price = 0;
  protected string $parent_category = '';
  protected string $child_category = '';
  protected string $stock_code = '';
  protected string $easy_dimension = '';
  protected string $image = '';
  protected array $alternative_images = [];

  public function getDescription(): string
  {
    return $this->description;
  }

  public function setDescription(string $description): void
  {
    $this->description = $description;
  }

  public function getAvailability(): string
  {
    return $this->availability;
  }

  public function setAvailability(string $availability): void
  {
    $this->availability = $availability;
  }

  public function isLaunched(): bool
  {
    return $this->launched;
  }

  public function setLaunched(bool $launched): void
  {
    $this->launched = $launched;
  }

  public function getManufacturer(): string
  {
    return $this->manufacturer;
  }

  public function setManufacturer(string $manufacturer): void
  {
    $this->manufacturer = $manufacturer;
  }

  public function getManufacturerPartCode(): string
  {
    return $this->manufacturer_part_code;
  }

  public function setManufacturerPartCode(string $manufacturer_part_code): void
  {
    $this->manufacturer_part_code = $manufacturer_part_code;
  }

  public function getExVatPrice(): int
  {
    return $this->ex_vat_price;
  }

  public function setExVatPrice(int $ex_vat_price): void
  {
    $this->ex_vat_price = $ex_vat_price;
  }

  public function getIncVatPrice(): int
  {
    return $this->inc_vat_price;
  }

  public function setIncVatPrice(int $inc_vat_price): void
  {
    $this->inc_vat_price = $inc_vat_price;
  }

  public function getParentCategory(): string
  {
    return $this->parent_category;
  }

  public function setParentCategory(string $parent_category): void
  {
    $this->parent_category = $parent_category;
  }

  public function getChildCategory(): string
  {
    return $this->child_category;
  }

  public function setChildCategory(string $child_category): void
  {
    $this->child_category = $child_category;
  }

  public function getStockCode(): string
  {
    return $this->stock_code;
  }

  public function setStockCode(string $stock_code): void
  {
    $this->stock_code = $stock_code;
  }

  public function getEasyDimension(): string
  {
    return $this->easy_dimension;
  }

  public function setEasyDimension(string $easy_dimension): void
  {
    $this->easy_dimension = $easy_dimension;
  }

  public function getImage(): string
  {
    return $this->image;
  }

  public function setImage(string $image): void
  {
    $this->image = $image;
  }

  public function getAlternativeImages(): array
  {
    return $this->alternative_images;
  }

  public function setAlternativeImages(array $alternative_images): void
  {
    $this->alternative_images = $alternative_images;
  }


}
