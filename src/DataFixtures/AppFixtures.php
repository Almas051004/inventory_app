<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setUsername('Администратор');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsBlocked(false);
        $admin->setEmailVerifiedAt(new \DateTimeImmutable());

        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);

        $admin2 = new User();
        $admin2->setEmail('facebook_2231587867244835@temp.local');
        $admin2->setUsername('Chelovek');
        $admin2->setRoles(['ROLE_ADMIN']);
        $admin2->setIsBlocked(false);
        $admin2->setPassword('');
        $admin2->setFacebookId('2231587867244835');
        $admin2->setAvatarUrl('');
        $admin2->setEmailVerifiedAt(new \DateTimeImmutable());

        $manager->persist($admin2);

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setUsername('Тестовый пользователь');
        $user->setRoles(['ROLE_USER']);
        $user->setIsBlocked(false);

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'user123');
        $user->setPassword($hashedPassword);

        $user->setEmailVerifiedAt(new \DateTimeImmutable());

        $manager->persist($user);


        $category = new Category();
        $category->setName('Equipment');
        $manager->persist($category);

        $inventory = new Inventory();
        $inventory->setTitle('Офисная техника');
        $inventory->setDescription('Инвентарь офисной техники в IT-отделе');
        $inventory->setCreator($admin);
        $inventory->setCategory($category);
        $inventory->setIsPublic(true);

        // Строковые поля
        $inventory->setCustomString1State(true);
        $inventory->setCustomString1Name('Инвентарный номер');
        $inventory->setCustomString1Description('Уникальный инвентарный номер оборудования');
        $inventory->setCustomString1ShowInTable(true);

        $inventory->setCustomString2State(true);
        $inventory->setCustomString2Name('Модель');
        $inventory->setCustomString2Description('Модель оборудования');
        $inventory->setCustomString2ShowInTable(true);

        // Числовые поля
        $inventory->setCustomInt1State(true);
        $inventory->setCustomInt1Name('Стоимость');
        $inventory->setCustomInt1Description('Стоимость оборудования в рублях');
        $inventory->setCustomInt1ShowInTable(true);

        // Булево поле
        $inventory->setCustomBool1State(true);
        $inventory->setCustomBool1Name('Работоспособно');
        $inventory->setCustomBool1Description('Оборудование в рабочем состоянии');
        $inventory->setCustomBool1ShowInTable(true);

        // Поле ссылки
        $inventory->setCustomLink1State(true);
        $inventory->setCustomLink1Name('Документация');
        $inventory->setCustomLink1Description('Ссылка на документацию или фото');
        $inventory->setCustomLink1ShowInTable(false);

        $manager->persist($inventory);

        $this->createTestItems($inventory, $admin, $manager);

        $validationInventory = new Inventory();
        $validationInventory->setTitle('Тестовый инвентарь с валидацией');
        $validationInventory->setDescription('Инвентарь для тестирования новых функций валидации полей');
        $validationInventory->setCategory($category);
        $validationInventory->setCreator($admin);
        $validationInventory->setIsPublic(true);

        // Строковое поле 1: Код сотрудника (3-10 символов, только буквы и цифры)
        $validationInventory->setCustomString1State(true);
        $validationInventory->setCustomString1Name('Код сотрудника');
        $validationInventory->setCustomString1Description('Уникальный код сотрудника (3-10 символов, буквы и цифры)');
        $validationInventory->setCustomString1ShowInTable(true);
        $validationInventory->setCustomString1MinLength(3);
        $validationInventory->setCustomString1MaxLength(10);
        $validationInventory->setCustomString1Regex('/^[A-Z0-9]+$/i');

        // Строковое поле 2: Email сотрудника
        $validationInventory->setCustomString2State(true);
        $validationInventory->setCustomString2Name('Email');
        $validationInventory->setCustomString2Description('Адрес электронной почты сотрудника');
        $validationInventory->setCustomString2ShowInTable(true);

        // Числовое поле 1: Возраст (18-65 лет)
        $validationInventory->setCustomInt1State(true);
        $validationInventory->setCustomInt1Name('Возраст');
        $validationInventory->setCustomInt1Description('Возраст сотрудника (18-65 лет)');
        $validationInventory->setCustomInt1ShowInTable(true);
        $validationInventory->setCustomInt1MinValue(18);
        $validationInventory->setCustomInt1MaxValue(65);

        // Числовое поле 2: Оклад (минимум 30000)
        $validationInventory->setCustomInt2State(true);
        $validationInventory->setCustomInt2Name('Оклад');
        $validationInventory->setCustomInt2Description('Месячный оклад сотрудника (минимум 30000 руб.)');
        $validationInventory->setCustomInt2ShowInTable(true);
        $validationInventory->setCustomInt2MinValue(30000);

        // Булево поле: Активен ли сотрудник
        $validationInventory->setCustomBool1State(true);
        $validationInventory->setCustomBool1Name('Активен');
        $validationInventory->setCustomBool1Description('Сотрудник активен в компании');
        $validationInventory->setCustomBool1ShowInTable(true);

        $validationInventory->setCustomIdFormat([
            'parts' => [
                [
                    'type' => 'fixed_text',
                    'text' => 'EMP'
                ],
                [
                    'type' => 'sequence',
                    'start_value' => 1,
                    'step' => 1,
                    'leading_zeros' => true,
                    'digits' => 3
                ]
            ]
        ]);

        $manager->persist($validationInventory);

        $this->createValidationTestItems($validationInventory, $admin, $manager);

        $manager->flush();
    }

    private function createTestItems(Inventory $inventory, User $creator, ObjectManager $manager): void
    {
        $sampleData = [
            [
                'custom_id' => 'COMP-001',
                'string1' => 'Ноутбук Dell',
                'string2' => 'XPS 13',
                'int1' => 85000,
                'bool1' => true,
                'text1' => 'Основной рабочий ноутбук для разработки'
            ],
            [
                'custom_id' => 'COMP-002',
                'string1' => 'Монитор Samsung',
                'string2' => 'LU28R590C',
                'int1' => 25000,
                'bool1' => true,
                'text1' => '4K монитор для дизайна'
            ],
            [
                'custom_id' => 'COMP-003',
                'string1' => 'Клавиатура Logitech',
                'string2' => 'MX Keys',
                'int1' => 12000,
                'bool1' => true,
                'text1' => 'Беспроводная клавиатура с подсветкой'
            ],
            [
                'custom_id' => 'COMP-004',
                'string1' => 'Мышь Logitech',
                'string2' => 'MX Master 3',
                'int1' => 8000,
                'bool1' => false,
                'text1' => 'Беспроводная мышь, требует ремонта'
            ],
            [
                'custom_id' => 'COMP-005',
                'string1' => 'Ноутбук Apple',
                'string2' => 'MacBook Pro',
                'int1' => 150000,
                'bool1' => true,
                'text1' => 'MacBook для тестирования кросс-платформенных приложений'
            ],
            [
                'custom_id' => 'COMP-006',
                'string1' => 'Монитор Dell',
                'string2' => 'U2720Q',
                'int1' => 45000,
                'bool1' => true,
                'text1' => '4K монитор для разработчика'
            ],
            [
                'custom_id' => 'COMP-007',
                'string1' => 'Клавиатура Keychron',
                'string2' => 'K8',
                'int1' => 6000,
                'bool1' => true,
                'text1' => 'Механическая клавиатура'
            ],
            [
                'custom_id' => 'COMP-008',
                'string1' => 'Мышь Apple',
                'string2' => 'Magic Mouse 2',
                'int1' => 7000,
                'bool1' => true,
                'text1' => 'Беспроводная мышь для Mac'
            ],
        ];

        foreach ($sampleData as $data) {
            $item = new Item();
            $item->setCustomId($data['custom_id']);
            $item->setInventory($inventory);
            $item->setCreatedBy($creator);

            $item->setCustomString1Value($data['string1']);
            $item->setCustomString2Value($data['string2']);
            $item->setCustomInt1Value($data['int1']);
            $item->setCustomBool1Value($data['bool1']);
            $item->setCustomText1Value($data['text1']);

            $manager->persist($item);
        }
    }

    private function createValidationTestItems(Inventory $inventory, User $creator, ObjectManager $manager): void
    {
        $sampleData = [
            [
                'custom_id' => 'EMP001',
                'string1' => 'EMP001', // Код сотрудника (3-10 символов, буквы+цифры)
                'string2' => 'ivan.petrov@company.com', // Email
                'int1' => 28, // Возраст (18-65)
                'int2' => 75000, // Оклад (минимум 30000)
                'bool1' => true, // Активен
                'text1' => 'Старший разработчик, опыт работы 5 лет'
            ],
            [
                'custom_id' => 'EMP002',
                'string1' => 'DEV2023', // Код сотрудника
                'string2' => 'maria.ivanova@company.com', // Email
                'int1' => 32, // Возраст
                'int2' => 85000, // Оклад
                'bool1' => true, // Активен
                'text1' => 'Ведущий разработчик, опыт работы 8 лет'
            ],
            [
                'custom_id' => 'EMP003',
                'string1' => 'MGR001', // Код сотрудника
                'string2' => 'alex.sidorov@company.com', // Email
                'int1' => 45, // Возраст
                'int2' => 120000, // Оклад
                'bool1' => true, // Активен
                'text1' => 'Менеджер проектов, опыт управления 12 лет'
            ],
            [
                'custom_id' => 'EMP004',
                'string1' => 'QATEST', // Код сотрудника
                'string2' => 'olga.kuznetsova@company.com', // Email
                'int1' => 26, // Возраст
                'int2' => 65000, // Оклад
                'bool1' => false, // Не активен (уволен)
                'text1' => 'Тестировщик ПО, опыт работы 3 года'
            ],
        ];

        foreach ($sampleData as $data) {
            $item = new Item();
            $item->setCustomId($data['custom_id']);
            $item->setInventory($inventory);
            $item->setCreatedBy($creator);

            $item->setCustomString1Value($data['string1']);
            $item->setCustomString2Value($data['string2']);
            $item->setCustomInt1Value($data['int1']);
            $item->setCustomInt2Value($data['int2']);
            $item->setCustomBool1Value($data['bool1']);
            $item->setCustomText1Value($data['text1']);

            $manager->persist($item);
        }
    }
}
