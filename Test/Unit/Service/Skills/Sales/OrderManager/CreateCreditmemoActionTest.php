<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\Sales\OrderManager;

use MagoAssistant\Mago\Service\Skills\Sales\OrderManager\CreateCreditmemoAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The texts the admin confirms on and the model reads. The action posts to order/{id}/refund, the
 * offline refund, so none of them may claim money goes back through the payment provider.
 */
final class CreateCreditmemoActionTest extends TestCase
{
    private CreateCreditmemoAction $action;

    protected function setUp(): void
    {
        // The texts under test touch none of the collaborators
        $this->action = (new \ReflectionClass(CreateCreditmemoAction::class))->newInstanceWithoutConstructor();
    }

    #[Test]
    public function itTellsTheAdminNoMoneyIsRefundedThroughThePaymentProvider(): void
    {
        $impacts = implode(' ', $this->action->getImpacts(['order_number' => '000000549'], 1));

        self::assertStringContainsString('Order #000000549 gets an offline credit memo', $impacts);
        self::assertStringContainsString('No money is refunded through the payment provider', $impacts);
        self::assertStringNotContainsString('original payment method', $impacts);
    }

    #[Test]
    public function itDescribesTheToolAsAnOfflineCreditMemo(): void
    {
        self::assertStringContainsString('offline credit memo', $this->action->getDescription());
        self::assertStringContainsString(
            'no money is refunded through the payment provider',
            $this->action->getDescription()
        );
    }

    #[Test]
    public function itInstructsTheModelNeverToClaimTheCustomerWasRefunded(): void
    {
        $instructions = $this->action->getInstructions();

        self::assertStringContainsString('does not refund any money through the payment provider', $instructions);
        self::assertStringContainsString('Never say the customer was refunded', $instructions);
    }
}
