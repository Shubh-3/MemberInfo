<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model\Validator;

use Magento\Framework\Exception\LocalizedException;
use Vendor\MemberInfo\Api\Data\MemberInfoInterface;

/**
 * Backend mirror of the checkout step's frontend validation (Req. 4: validation
 * must be performed on both the frontend and backend). The frontend rules exist
 * for UX; this class is the actual security/data-integrity boundary.
 */
class MemberInfoValidator
{
    private const SSN_PATTERN = '/^\d{3}-?\d{2}-?\d{4}$/';

    public function validate(MemberInfoInterface $member): void
    {
        if (trim($member->getFirstName()) === '') {
            throw new LocalizedException(__('First name is required.'));
        }

        if (trim($member->getLastName()) === '') {
            throw new LocalizedException(__('Last name is required.'));
        }

        $this->validateDob($member->getDob());
        $this->validateSsn($member->getSsn());
    }

    private function validateDob(string $dob): void
    {
        $date = \DateTime::createFromFormat('Y-m-d', $dob);
        $errors = \DateTime::getLastErrors();

        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new LocalizedException(__('Date of birth must be a valid date (YYYY-MM-DD).'));
        }

        if ($date > new \DateTime('today')) {
            throw new LocalizedException(__('Date of birth cannot be in the future.'));
        }
    }

    private function validateSsn(string $ssn): void
    {
        if (!preg_match(self::SSN_PATTERN, $ssn)) {
            throw new LocalizedException(__('SSN must be a valid format (e.g. 123-45-6789).'));
        }
    }
}
