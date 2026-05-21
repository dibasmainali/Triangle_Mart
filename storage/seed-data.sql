-- Triangle Mart starter data using plain text passwords for coursework prototype only.
-- Run after your DDL.

INSERT INTO Users(User_Id, User_Type, First_Name, Last_Name, Email, Password, Phone, Address, Email_Verified, Approval_Status)
VALUES('U0001','ADMIN','Admin','User','admin@trianglemart.com','password123','000','Cleckhuddersfax','Y','APPROVED');

INSERT INTO Users(User_Id, User_Type, First_Name, Last_Name, Email, Password, Business_Name, Business_Description, Email_Verified, Approval_Status)
VALUES('T0002','TRADER','Ben','Butcher','butcher@trianglemart.com','password123','Cleck Butchers','Fresh local butcher products','Y','APPROVED');

INSERT INTO Users(User_Id, User_Type, First_Name, Last_Name, Email, Password, Email_Verified, Approval_Status)
VALUES('C0003','CUSTOMER','Test','Customer','customer@trianglemart.com','password123','Y','APPROVED');

INSERT INTO Shop(Shop_Id, User_Id, Shop_Name, Shop_Address) VALUES('S0001','T0002','Cleck Butchers','High Street');

INSERT INTO Category(Category_Id, Category_Name) VALUES('CAT001','Meat');
INSERT INTO Category(Category_Id, Category_Name) VALUES('CAT002','Bakery');
INSERT INTO Category(Category_Id, Category_Name) VALUES('CAT003','Fish');
INSERT INTO Category(Category_Id, Category_Name) VALUES('CAT004','Fruit and Vegetables');
INSERT INTO Category(Category_Id, Category_Name) VALUES('CAT005','Deli');

INSERT INTO Product(Product_Id, Shop_Id, Category_Id, Name, Description, Price, Quantity_Per_Item, Stock_Available, Min_Order, Max_Order, Allergy_Info)
VALUES('PR0001','S0001','CAT001','Local Beef Mince','Fresh local beef mince, 500g pack.',5.50,1,30,1,5,'None');

INSERT INTO Collection_Slot(Collection_Slot_Id, Collection_Date, Time_Range, Max_Orders, Current_Orders)
VALUES('CS0001', NEXT_DAY(TRUNC(SYSDATE)+1,'WEDNESDAY'), '10-13', 20, 0);
INSERT INTO Collection_Slot(Collection_Slot_Id, Collection_Date, Time_Range, Max_Orders, Current_Orders)
VALUES('CS0002', NEXT_DAY(TRUNC(SYSDATE)+1,'THURSDAY'), '13-16', 20, 0);
INSERT INTO Collection_Slot(Collection_Slot_Id, Collection_Date, Time_Range, Max_Orders, Current_Orders)
VALUES('CS0003', NEXT_DAY(TRUNC(SYSDATE)+1,'FRIDAY'), '16-19', 20, 0);

COMMIT;
